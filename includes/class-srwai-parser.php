<?php
if (!defined('ABSPATH')) exit;

class SRWAI_Parser {
    public static function inspect($path, $ext, $limit = 3) {
        $ext = strtolower($ext);
        $limit = max(1, min(3, (int) $limit));
        if ($ext === 'csv') return self::csv_sample($path, $limit);
        if ($ext === 'xlsx') return self::xlsx_sample($path, $limit);
        if ($ext === 'xml') return self::xml_sample($path, $limit);
        throw new Exception('Unsupported source file.');
    }

    public static function batch($path, $ext, $offset, $limit) {
        $ext = strtolower($ext);
        if ($ext === 'csv') return self::csv_batch($path, $offset, $limit);
        if ($ext === 'xlsx') return self::xlsx_batch($path, $offset, $limit);
        if ($ext === 'xml') return self::xml_batch($path, $offset, $limit);
        throw new Exception('Unsupported source file.');
    }

    public static function count($path, $ext) {
        $ext = strtolower($ext);
        if ($ext === 'csv') return self::count_csv($path);
        if ($ext === 'xlsx') return self::xlsx_count($path);
        if ($ext === 'xml') return self::xml_count($path);
        throw new Exception('Unsupported source file.');
    }

    private static function clean_headers($headers) {
        $out = array();
        foreach ((array) $headers as $i => $v) {
            $v = trim((string) $v);
            if ($v === '') $v = 'column_' . ((int) $i + 1);
            $base = $v; $n = 2;
            while (in_array($v, $out, true)) $v = $base . '_' . $n++;
            $out[] = $v;
        }
        return $out;
    }

    private static function row($headers, $values) {
        $out = array();
        foreach ($headers as $i => $key) $out[$key] = isset($values[$i]) ? $values[$i] : '';
        return $out;
    }

    private static function csv_file($path) {
        try {
            $file = new SplFileObject($path, 'rb');
        } catch (RuntimeException $e) {
            throw new Exception('Could not open CSV.');
        }
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        return $file;
    }

    private static function csv_sample($path, $limit) {
        $fh = self::csv_file($path);
        $headers = $fh->fgetcsv();
        if ($headers === false) throw new Exception('CSV has no header row.');
        $headers = self::clean_headers($headers);
        $rows = array();
        while (count($rows) < $limit && ($values = $fh->fgetcsv()) !== false) {
            if (count($values) === 1 && trim((string) $values[0]) === '') continue;
            $rows[] = self::row($headers, $values);
        }
        return array('headers' => $headers, 'sample' => $rows, 'count' => null, 'count_known' => false, 'is_url_csv' => false);
    }

    private static function count_csv($path) {
        $fh = self::csv_file($path);
        $count = -1;
        while ($fh->fgetcsv() !== false) $count++;
        return max(0, $count);
    }

    private static function csv_batch($path, $offset, $limit) {
        $fh = self::csv_file($path);
        $headers = $fh->fgetcsv();
        if ($headers === false) throw new Exception('CSV has no header row.');
        $headers = self::clean_headers($headers);
        $position = 0;
        $rows = array();
        while (($values = $fh->fgetcsv()) !== false) {
            if (count($values) === 1 && trim((string) $values[0]) === '') continue;
            if ($position++ < (int) $offset) continue;
            $rows[] = self::row($headers, $values);
            if (count($rows) >= (int) $limit) break;
        }
        return $rows;
    }

    /* ----------------------------- XLSX ----------------------------- */

    private static function open_zip($path) {
        if (!class_exists('ZipArchive')) throw new Exception('PHP ZipArchive extension is required to read XLSX files. Please enable the PHP zip extension on the server.');
        if (!is_readable($path)) throw new Exception('The uploaded XLSX file is not readable.');
        $zip = new ZipArchive();
        $result = $zip->open($path);
        if ($result !== true) throw new Exception('Could not open the XLSX package. The file may be corrupt or not a valid XLSX file.');
        return $zip;
    }

    private static function first_sheet_name($zip) {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!$workbook || !$rels) throw new Exception('XLSX workbook metadata is missing.');

        // The workbook metadata is tiny. Regex is intentionally used here so
        // XLSX inspection does not depend on SimpleXML/DOM and remains vendor-neutral.
        $rid = '';
        if (preg_match('/<sheet\\b[^>]*\\br:id=["\\\']([^"\\\']+)["\\\']/i', $workbook, $match)) $rid = $match[1];
        if ($rid === '') throw new Exception('Could not read the first worksheet relationship.');

        $target = '';
        $quoted = preg_quote($rid, '/');
        if (preg_match('/<Relationship\\b[^>]*\\bId=["\\\']'.$quoted.'["\\\'][^>]*\\bTarget=["\\\']([^"\\\']+)["\\\']/i', $rels, $match)) $target = $match[1];
        if ($target === '' && preg_match('/<Relationship\\b[^>]*\\bTarget=["\\\']([^"\\\']+)["\\\'][^>]*\\bId=["\\\']'.$quoted.'["\\\']/i', $rels, $match)) $target = $match[1];
        if ($target === '') throw new Exception('Could not locate the first XLSX worksheet.');

        $target = ltrim($target, '/');
        if (strpos($target, 'xl/') !== 0) $target = 'xl/' . ltrim($target, '/');
        $target = str_replace('../', '', $target);
        if (!$zip->locateName($target)) throw new Exception('The first XLSX worksheet file is missing.');
        return $target;
    }

    private static function extract_zip_entry($zip, $name, $prefix) {
        $tmp = wp_tempnam($prefix);
        if (!$tmp) throw new Exception('Could not create a temporary file for XLSX processing.');
        try {
            $out = new SplFileObject($tmp, 'wb');
        } catch (RuntimeException $e) {
            wp_delete_file($tmp);
            throw new Exception('Could not create the XLSX temporary file.');
        }
        $stream = $zip->getStream($name);
        if (!$stream) {
            wp_delete_file($tmp);
            throw new Exception('Could not read XLSX worksheet data.');
        }
        while (!feof($stream)) {
            $buffer = stream_get_contents($stream, 1024 * 1024);
            if ($buffer === false) {
                wp_delete_file($tmp);
                throw new Exception('Could not stream XLSX worksheet data.');
            }
            if ($buffer !== '') $out->fwrite($buffer);
        }
        unset($out);
        return $tmp;
    }

    private static function xmlreader_open_file($path) {
        if (!class_exists('XMLReader')) throw new Exception('PHP XMLReader extension is required to read XLSX files.');
        $reader = new XMLReader();
        $flags = LIBXML_NONET | LIBXML_COMPACT;
        if (defined('LIBXML_PARSEHUGE')) $flags |= LIBXML_PARSEHUGE;
        if (!@$reader->open($path, null, $flags)) throw new Exception('Could not stream XLSX worksheet XML.');
        return $reader;
    }

    private static function xlsx_row_cells_xml($xml) {
        $cells = array();
        if (!class_exists('XMLReader')) return $cells;
        $reader = new XMLReader();
        $flags = LIBXML_NONET | LIBXML_COMPACT;
        if (defined('LIBXML_PARSEHUGE')) $flags |= LIBXML_PARSEHUGE;
        if (!@$reader->XML($xml, null, $flags)) return $cells;
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') continue;
            $ref = (string) $reader->getAttribute('r');
            if (!preg_match('/^([A-Z]+)\d+$/i', $ref, $match)) continue;
            $col = self::col_index(strtoupper($match[1]));
            $type = (string) $reader->getAttribute('t');
            $depth = $reader->depth;
            $value = '';
            $capture = false;
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'v') {
                    $capture = true;
                } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't' && $type === 'inlineStr') {
                    $capture = true;
                } elseif (($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) && $capture) {
                    $value .= $reader->value;
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'v') {
                    $capture = false;
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 't' && $type === 'inlineStr') {
                    $capture = false;
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'c' && $reader->depth === $depth) {
                    break;
                }
            }
            $cells[$col] = array('value' => $value, 'type' => $type);
        }
        $reader->close();
        return $cells;
    }

    private static function col_index($letters) {
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) $n = $n * 26 + (ord($letters[$i]) - 64);
        return $n - 1;
    }

    private static function load_needed_shared_strings($zip, $needed) {
        $out = array();
        if (!$needed) return $out;
        if (!class_exists('XMLReader')) return $out;
        $tmp = self::extract_zip_entry($zip, 'xl/sharedStrings.xml', 'srwai-shared');
        try {
            $reader = self::xmlreader_open_file($tmp);
            $index = -1; $active = false; $depth = -1; $text = '';
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                    $index++;
                    $active = isset($needed[$index]);
                    $depth = $reader->depth;
                    $text = '';
                    continue;
                }
                if (!$active) continue;
                if ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                    $text .= $reader->value;
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si' && $reader->depth === $depth) {
                    $out[$index] = $text;
                    $active = false;
                    if (count($out) >= count($needed)) break;
                }
            }
            $reader->close();
        } finally {
            wp_delete_file($tmp);
        }
        return $out;
    }

    private static function xlsx_rows($path, $offset, $limit, $inspect_only = false) {
        $zip = self::open_zip($path);
        try {
            $sheet = self::first_sheet_name($zip);
            $sheet_tmp = self::extract_zip_entry($zip, $sheet, 'srwai-sheet');
        } finally {
            $zip->close();
        }

        $header_raw = array();
        $raw_rows = array();
        $data_index = -1;
        $needed_shared = array();
        $wanted_start = $inspect_only ? 0 : max(0, (int) $offset);
        $wanted_end = $wanted_start + max(1, (int) $limit);
        try {
            $reader = self::xmlreader_open_file($sheet_tmp);
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') continue;
                $row_xml = $reader->readOuterXML();
                $cells = self::xlsx_row_cells_xml($row_xml);
                if ($header_raw === array()) {
                    $max = $cells ? max(array_keys($cells)) : -1;
                    if ($max < 0) continue;
                    for ($i = 0; $i <= $max; $i++) {
                        $header_raw[$i] = isset($cells[$i]) ? $cells[$i]['value'] : '';
                        if (isset($cells[$i]) && $cells[$i]['type'] === 's' && ctype_digit((string) $cells[$i]['value'])) {
                            $needed_shared[(int) $cells[$i]['value']] = true;
                        }
                    }
                    continue;
                }
                $data_index++;
                if ($data_index < $wanted_start) continue;
                if ($data_index >= $wanted_end) break;
                foreach ($cells as $cell) {
                    if ($cell['type'] === 's' && ctype_digit((string) $cell['value'])) $needed_shared[(int) $cell['value']] = true;
                }
                $raw_rows[] = $cells;
                if (count($raw_rows) >= $limit) break;
            }
            $reader->close();
        } finally {
            wp_delete_file($sheet_tmp);
        }

        if ($header_raw === array()) throw new Exception('XLSX has no header row.');
        $zip = self::open_zip($path);
        try {
            $shared = self::load_needed_shared_strings($zip, $needed_shared);
        } finally {
            $zip->close();
        }

        $headers = array();
        foreach ($header_raw as $i => $value) {
            $headers[$i] = (isset($needed_shared[(int) $value]) && ctype_digit((string) $value)) ? (isset($shared[(int) $value]) ? $shared[(int) $value] : '') : $value;
        }
        $headers = self::clean_headers($headers);

        $rows = array();
        foreach ($raw_rows as $cells) {
            $max = max(count($headers) - 1, $cells ? max(array_keys($cells)) : -1);
            $values = array();
            for ($i = 0; $i <= $max; $i++) {
                $value = '';
                if (isset($cells[$i])) {
                    $cell = $cells[$i];
                    $value = $cell['value'];
                    if ($cell['type'] === 's') $value = isset($shared[(int) $value]) ? $shared[(int) $value] : '';
                    elseif ($cell['type'] === 'b') $value = ($value === '1') ? '1' : '0';
                }
                $values[$i] = $value;
            }
            $rows[] = self::row($headers, $values);
        }
        return array('headers' => $headers, 'rows' => $rows);
    }

    private static function xlsx_sample($path, $limit) {
        $result = self::xlsx_rows($path, 0, $limit, true);
        return array('headers' => $result['headers'], 'sample' => $result['rows'], 'count' => null, 'count_known' => false, 'is_url_csv' => false);
    }

    private static function xlsx_batch($path, $offset, $limit) {
        $result = self::xlsx_rows($path, $offset, $limit, false);
        return $result['rows'];
    }

    private static function xlsx_count($path) {
        $zip = self::open_zip($path);
        try { $sheet = self::first_sheet_name($zip); $tmp = self::extract_zip_entry($zip, $sheet, 'srwai-count'); }
        finally { $zip->close(); }
        try {
            $reader = self::xmlreader_open_file($tmp);
            $count = 0; $header_seen = false;
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
                    if (!$header_seen) $header_seen = true; else $count++;
                }
            }
            $reader->close();
        } finally { wp_delete_file($tmp); }
        return max(0, $count);
    }

    /* ------------------------------ XML ------------------------------ */

    private static function xml_reader($path) {
        if (!class_exists('XMLReader')) throw new Exception('PHP XMLReader extension is required to read XML files.');
        $reader = new XMLReader();
        if (!@$reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) throw new Exception('Could not read XML file.');
        return $reader;
    }

    private static function xml_record_from_xml($xml) {
        if (!function_exists('simplexml_load_string')) throw new Exception('PHP SimpleXML is required to read XML files.');
        libxml_use_internal_errors(true);
        $s = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        if (!$s) throw new Exception('Invalid XML record.');
        $row = array(); $field_style = false;
        foreach ($s->children() as $child) if ($child->getName() === 'field' && isset($child['name'])) { $field_style = true; break; }
        if ($field_style) {
            foreach ($s->children() as $child) { $name = (string) $child['name']; if ($name !== '') $row[$name] = (string) $child; }
            return $row;
        }
        foreach ($s->children() as $key => $value) $row[(string) $key] = $value->children()->count() ? wp_json_encode(self::xml_value($value)) : trim((string) $value);
        foreach ($s->attributes() as $key => $value) $row['@' . (string) $key] = (string) $value;
        return $row;
    }

    private static function xml_value($value) {
        if (!$value->children()) return trim((string) $value);
        $array = array(); foreach ($value->children() as $key => $child) $array[(string) $key] = self::xml_value($child); return $array;
    }

    private static function xml_records($path, $offset = 0, $limit = 0) {
        $reader = self::xml_reader($path); $records = array(); $index = -1; $headers = array();
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === 1) {
                $xml = $reader->readOuterXML(); $index++; $row = self::xml_record_from_xml($xml);
                if (!$headers) $headers = array_keys($row); else foreach (array_keys($row) as $key) if (!in_array($key, $headers, true)) $headers[] = $key;
                if ($index < (int) $offset) continue;
                $records[] = $row;
                if ($limit && count($records) >= (int) $limit) break;
            }
        }
        $reader->close();
        return array('headers' => $headers, 'rows' => $records, 'count_seen' => $index + 1);
    }

    private static function xml_sample($path, $limit) {
        $result = self::xml_records($path, 0, $limit);
        if (!$result['rows']) throw new Exception('No XML records found.');
        return array('headers' => $result['headers'], 'sample' => $result['rows'], 'count' => null, 'count_known' => false, 'is_url_csv' => false);
    }

    private static function xml_batch($path, $offset, $limit) { $result = self::xml_records($path, $offset, $limit); return $result['rows']; }
    private static function xml_count($path) {
        $reader = self::xml_reader($path); $count = 0;
        while ($reader->read()) if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === 1) { $count++; $reader->next(); }
        $reader->close(); return $count;
    }
}
