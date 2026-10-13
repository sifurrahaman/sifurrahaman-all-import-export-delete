jQuery(function($) {
     function esc(v) {
        return $('<div>').text(v==null?'':v).html();

        }
     function asArray(v) {
        if(Array.isArray(v))return v;
        if(v&&typeof v==='object')return Object.keys(v).map(function(k) {
            return v[k];

            });
        return [];

        }
     function asObject(v) {
        return(v&&typeof v==='object'&&!Array.isArray(v))?v: {

            };

        }
     function normKey(v) {
        return String(v==null?'':v).toLowerCase().replace(/[^a-z0-9]+/g, '');

        }
     function findSource(fields, target, aliases) {
        fields=asArray(fields);
        var want=normKey(target);
        for(var i=0;
        i<fields.length;
        i++) {
            if(normKey(fields[i])===want)return fields[i];

            }
        aliases=aliases||[];
        for(var a=0;
        a<aliases.length;
        a++) {
            var aw=normKey(aliases[a]);
            for(var j=0;
            j<fields.length;
            j++) {
                if(normKey(fields[j])===aw)return fields[j];

                }

            }
        return '';

        }
     function select(name, fields, selected) {
        fields=asArray(fields);
        var h='<select class="form-select" name="'+esc(name)+'"><option value="">— Not mapped —</option>';
        fields.forEach(function(s) {
            h+='<option value="'+esc(s)+'"'+(selected===s?' selected':'')+'>'+esc(s)+'</option>';

            });
        return h+'</select>';

        }
     function mappingHTML(d) {
         d=d|| {

            };
        d.target=d.target|| {

            };
        d.target.standard=asObject(d.target.standard);
        d.target.meta=asArray(d.target.meta);
        d.target.taxonomies=asArray(d.target.taxonomies);
        d.source_fields=asArray(d.source_fields);
         var info=d.count===null?'Record count will be calculated when the import starts.':esc(d.count)+' records detected.';
         var h='<div class="srwai-mapping-fullwidth"><div class="card srwai-card srwai-mapping-card"><div class="card-body p-4">' +'<div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h2 class="h4 mb-1">Field Mapping</h2><p class="text-secondary mb-0">'+info+' Only a small sample was inspected.</p></div><span class="badge text-bg-secondary">'+esc(String(d.source_type||'').toUpperCase())+'</span></div>' +'<form id="srwai-mapping-form" class="mt-4"><input type="hidden" name="job_id" value="'+esc(d.job_id)+'">' +'<h3 class="h5">Standard WordPress Fields</h3><div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Target</th><th>Source Field</th></tr></thead><tbody>';
         Object.keys(d.target.standard).forEach(function(k) {
            var auto='';
            var sf=d.source_fields;
            if(k==='post_title')auto=findSource(sf, k, ['title', 'product_name', 'productname']);
            else if(k==='post_content')auto=findSource(sf, k, ['content', 'long_description', 'description', 'detailed_description']);
            else if(k==='post_excerpt')auto=findSource(sf, k, ['short_description', 'excerpt', 'summary']);
            else if(k==='post_date')auto=findSource(sf, k, ['date', 'posting_date', 'postingdate']);
            h+='<tr><td><strong>'+esc(d.target.standard[k])+'</strong><div class="small text-secondary">'+esc(k)+'</div></td><td>'+select('standard['+k+']', sf, auto)+'</td></tr>';

            });
         h+='</tbody></table></div><h3 class="h5 mt-4">Custom Fields / Post Meta</h3><div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Meta Key</th><th>Source Field</th></tr></thead><tbody>';
         if(!d.target.meta.length)h+='<tr><td colspan="2" class="text-secondary">No registered custom meta fields were detected for this post type.</td></tr>';
         d.target.meta.forEach(function(k) {
            h+='<tr><td><code>'+esc(k)+'</code></td><td>'+select('meta['+k+']', d.source_fields, findSource(d.source_fields, k, []))+'</td></tr>';

            });
         h+='</tbody></table></div><h3 class="h5 mt-4">Taxonomies</h3><div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Taxonomy</th><th>Source Field</th></tr></thead><tbody>';
         if(!d.target.taxonomies.length)h+='<tr><td colspan="2" class="text-secondary">No taxonomies are attached to this post type.</td></tr>';
         d.target.taxonomies.forEach(function(k) {
            h+='<tr><td><strong>'+esc(k)+'</strong></td><td>'+select('taxonomies['+k+']', d.source_fields, findSource(d.source_fields, k, []))+'</td></tr>';

            });
         h+='</tbody></table></div><div class="mt-4"><label class="form-label fw-semibold">Featured Image Source</label>'+select('featured_image', d.source_fields, findSource(d.source_fields, 'featured_image', ['image', 'image_url', 'featuredimage']))+'</div><div class="d-flex flex-wrap gap-2 mt-4"><a class="btn btn-outline-secondary btn-lg" href="'+esc(SRWAI.importUrl+(d.job_id?'&job_id='+encodeURIComponent(d.job_id):''))+'" id="srwai-back-import">← Back to Import Setup</a><button type="submit" class="btn btn-success btn-lg">Start Import →</button></div></form></div></div></div>';
         return h;
         }
     function replacePageFromUrl(url, replace) {
         var $root=$('.srwai-wrap');
         if(!$root.length)return window.location.href=url;
         $('body').addClass('srwai-soft-loading');
         $.get(url, function(html) {
             var parsed=$(html), newRoot=parsed.find('.srwai-wrap').first();
             if(!newRoot.length) {
                window.location.href=url;
                return;

                }
             $root.replaceWith(newRoot);
             if(replace)history.replaceState( {
                srwai:true
                }, '', url);
            else history.pushState( {
                srwai:true
                }, '', url);
             initPage();
             }).fail(function() {
            window.location.href=url;

            }).always(function() {
            $('body').removeClass('srwai-soft-loading');

            });
         }
     function loadMappingPage() {
        var box=$('#srwai-mapping-page');
        if(!box.length)return;
        $.post(SRWAI.ajaxurl, {
            action:'srwai_get_mapping', nonce:SRWAI.nonce, job_id:box.data('job-id')
            }, function(r) {
            if(!r.success) {
                box.html('<div class="alert alert-danger">'+esc(r.data)+'</div>');
                return;

                }
            box.html(mappingHTML(r.data));

            }).fail(function() {
            box.html('<div class="alert alert-danger">Could not load the field mapping. Check debug.log.</div>');

            });

        }
     $(document).off('submit.srwai', '#srwai-import-form').on('submit.srwai', '#srwai-import-form', function(e) {
        e.preventDefault();
        var f=this, b=$(this).find('button[type=submit]'), existing=$(f).find('[name=existing_job_id]').val(), file=$(f).find('[name=source_file]')[0];
        b.prop('disabled', true).text('Loading field mapping...');
        var fd=new FormData(f);
        if(existing && !(file&&file.files&&file.files.length)) {
             $.post(SRWAI.ajaxurl, {
                action:'srwai_reopen_mapping', nonce:SRWAI.nonce, job_id:existing
                }, function(r) {
                if(!r.success) {
                    alert(r.data);
                    b.prop('disabled', false).text('Continue to Field Mapping');
                    return;

                    }
                replacePageFromUrl(r.data.redirect, false);

                }).fail(function() {
                alert('Could not reopen the field mapping. Check debug.log.');
                b.prop('disabled', false).text('Continue to Field Mapping');

                });
            return;
             }
         b.text('Inspecting source...');
        $.ajax( {
            url:SRWAI.ajaxurl, type:'POST', data:fd, processData:false, contentType:false, timeout:45000
            }).done(function(r) {
            if(!r.success) {
                alert(r.data);
                b.prop('disabled', false).text('Continue to Field Mapping');
                return;

                }
            replacePageFromUrl(r.data.redirect, false);

            }).fail(function(x) {
            var msg='The server did not complete the source inspection request.';
            if(x.status===0&&x.statusText==='timeout')msg='Source inspection timed out after 45 seconds. No posts were imported.';
            if(x.status===413)msg='The uploaded file is larger than the server upload limit (HTTP 413).';
            if(x.status===500)msg='The server returned HTTP 500. Check wp-content/debug.log for the exact PHP error.';
            alert(msg);
            b.prop('disabled', false).text('Continue to Field Mapping');

            });

        });
     $(document).off('submit.srwaiMapping', '#srwai-mapping-form').on('submit.srwaiMapping', '#srwai-mapping-form', function(e) {
        e.preventDefault();
        var form=$(this), button=form.find('button[type=submit]'), m= {
            standard: {

                }, meta: {

                }, taxonomies: {

                }, featured_image:''
            };
        form.serializeArray().forEach(function(x) {
            var z=x.name.match(/^(standard|meta|taxonomies)\[(.+)\]$/);
            if(z)m[z[1]][z[2]]=x.value;
            else if(x.name==='featured_image')m.featured_image=x.value;

            });
        if(!m.standard.post_title) {
            alert('Please map the Title field before starting.');
            return;

            }
        button.prop('disabled', true).text('Preparing import...');
        $.post(SRWAI.ajaxurl, {
            action:'srwai_save_mapping', nonce:SRWAI.nonce, job_id:form.find('[name=job_id]').val(), mapping:JSON.stringify(m)
            }, function(r) {
            if(!r.success) {
                alert(r.data);
                button.prop('disabled', false).text('Start Import →');
                return;

                }
            replacePageFromUrl(r.data.redirect, false);

            }).fail(function() {
            alert('Could not save the field mapping. Check debug.log.');
            button.prop('disabled', false).text('Start Import →');

            });

        });
     $(document).off('click.srwaiBack', '#srwai-back-import').on('click.srwaiBack', '#srwai-back-import', function(e) {
        e.preventDefault();
        replacePageFromUrl(this.href, false);

        });
     function formatElapsed(seconds) {
        seconds=Math.max(0, parseInt(seconds, 10)||0);
        var h=Math.floor(seconds/3600), m=Math.floor((seconds%3600)/60), s=seconds%60;
        return String(h).padStart(2, '0')+':'+String(m).padStart(2, '0')+':'+String(s).padStart(2, '0');

        }
     function updateProgress(d) {
        var total=parseInt(d.total, 10)||0, pos=parseInt(d.position, 10)||0, pct=total?Math.min(100, Math.round(pos/total*100)):0;
        $('#srwai-progress-bar').css('width', pct+'%').text(pct+'%');
        $('.srwai-progress-percent').text(pct+'%');
        $('.srwai-progress-count').text(pos+' / '+total+' rows'+(d.status==='complete'?' · done':''));
        $('#srwai-stat-processed').text(pos);
        $('#srwai-stat-total').text(total);
        $('#srwai-stat-imported').text(d.total_created||0);
        $('#srwai-stat-updated').text(d.total_updated||0);
        $('#srwai-stat-skipped').text(d.total_skipped||0);
        $('#srwai-stat-remaining').text(Math.max(0, total-pos));
        if(d.source_name)$('#srwai-progress-file').text(d.source_name);
        if(d.started_at) {
            var now=Math.floor(Date.now()/1000);
            var end=d.finished_at||now;
            $('#srwai-progress-time').text('Elapsed: '+formatElapsed(end-d.started_at));

            }

        }
     var progressTimer=null, progressRunning=false, stopRequested=false;
     function setProgressButton(status) {
        var b=$('#srwai-push-import');
        if(!b.length)return;
        b.attr('data-state', status);
        if(status==='running') {
            b.text('Stop Import').removeClass('button-primary').addClass('button-secondary').prop('disabled', false);

            }
        else if(status==='paused') {
            b.text('Resume Import').removeClass('button-secondary').addClass('button-primary').prop('disabled', false);

            }
        else if(status==='complete') {
            b.text('Import Complete').prop('disabled', true).removeClass('button-primary').addClass('button-secondary');

            }
        else {
            b.text('Push Import').removeClass('button-secondary').addClass('button-primary').prop('disabled', false);

            }

        }
     function showResultMessage(type, msg) {
        $('#srwai-progress-message').html('<div class="alert alert-'+type+'">'+esc(msg)+'</div>');

        }
     function runOneBatch() {
        if(progressRunning)return;
        stopRequested=false;
        progressRunning=true;
        var id=$('#srwai-progress-page').data('job-id');
        $.post(SRWAI.ajaxurl, {
            action:'srwai_start_job', nonce:SRWAI.nonce, job_id:id
            }, function(r) {
            progressRunning=false;
            if(!r.success) {
                showResultMessage('danger', r.data);
                setProgressButton('paused');
                return;

                }
            updateProgress(r.data);
            if(r.data.errors&&r.data.errors.length)showResultMessage('warning', r.data.errors.join('\n'));
            if(r.data.status==='complete') {
                clearTimeout(progressTimer);
                $('.srwai-progress-title').text('Import complete');
                setProgressButton('complete');
                return;

                }
            if(stopRequested) {
                clearTimeout(progressTimer);
                $('.srwai-progress-title').text('Import paused');
                setProgressButton('paused');
                return;

                }
            if(r.data.status==='running') {
                setProgressButton('running');
                progressTimer=setTimeout(runOneBatch, 300);

                }

            }).fail(function() {
            progressRunning=false;
            if(stopRequested) {
                setProgressButton('paused');
                return;

                }
            showResultMessage('danger', 'Import request failed. The saved progress is preserved; use Resume Import to continue.');
            setProgressButton('paused');

            });

        }
     function pauseImport() {
        if(progressRunning&&stopRequested)return;
        stopRequested=true;
        clearTimeout(progressTimer);
        var id=$('#srwai-progress-page').data('job-id');
        $('.srwai-progress-title').text('Stopping import...');
        $.post(SRWAI.ajaxurl, {
            action:'srwai_pause_job', nonce:SRWAI.nonce, job_id:id
            }, function(r) {
            if(!r.success) {
                stopRequested=false;
                showResultMessage('danger', r.data);
                return;

                }
            updateProgress(r.data);
            if(r.data.status==='paused') {
                progressRunning=false;
                $('.srwai-progress-title').text('Import paused');
                showResultMessage('info', 'Import stopped. Click Resume Import to continue from the saved position.');
                setProgressButton('paused');

                }
            else if(r.data.status==='complete') {
                setProgressButton('complete');

                }

            }).fail(function() {
            stopRequested=false;
            showResultMessage('danger', 'Could not stop the import. Please try again.');

            });

        }
     function loadProgressPage() {
        var page=$('#srwai-progress-page');
        if(!page.length)return;
        var id=page.data('job-id');
        $.post(SRWAI.ajaxurl, {
            action:'srwai_job_status', nonce:SRWAI.nonce, job_id:id
            }, function(r) {
            if(!r.success) {
                showResultMessage('danger', r.data);
                return;

                }
            updateProgress(r.data);
            setProgressButton(r.data.status);
            if(r.data.status==='complete')$('.srwai-progress-title').text('Import complete');
            else if(r.data.status==='paused') {
                $('.srwai-progress-title').text('Import paused');

                }
            else if(r.data.status==='running') {
                $('.srwai-progress-title').text('Import in progress');
                runOneBatch();

                }
            else {
                $('.srwai-progress-title').text('Ready to import');
                runOneBatch();

                }

            }).fail(function() {
            showResultMessage('danger', 'Could not load the saved import status.');

            });

        }
     $(document).off('click.srwaiPush', '#srwai-push-import').on('click.srwaiPush', '#srwai-push-import', function() {
        var b=$(this), state=b.attr('data-state')||'ready';
        if(state==='complete')return;
        if(state==='running') {
            pauseImport();
            return;

            }
        if(progressRunning)return;
        showResultMessage('info', 'Import is running. Please keep this page open.');
        $('.srwai-progress-title').text('Import in progress');
        setProgressButton('running');
        runOneBatch();

        });
     $(document).off('click.srwaiSummary', '#srwai-summary-btn').on('click.srwaiSummary', '#srwai-summary-btn', function() {
        var d=$('#srwai-progress-page').data('job-id');
        $.post(SRWAI.ajaxurl, {
            action:'srwai_job_status', nonce:SRWAI.nonce, job_id:d
            }, function(r) {
            if(!r.success) {
                alert(r.data);
                return;

                }
            var x=r.data;
            alert('Processed: '+x.position+'\nTotal: '+x.total+'\nImported: '+x.total_created+'\nUpdated: '+x.total_updated+'\nSkipped: '+x.total_skipped+'\nRemaining: '+Math.max(0, x.total-x.position));

            });

        });
     $(document).off('click.srwaiFailed', '#srwai-failed-btn').on('click.srwaiFailed', '#srwai-failed-btn', function() {
        var id=$('#srwai-progress-page').data('job-id');
        $.post(SRWAI.ajaxurl, {
            action:'srwai_job_status', nonce:SRWAI.nonce, job_id:id
            }, function(r) {
            if(!r.success) {
                alert(r.data);
                return;

                }
            var e=r.data.errors||[];
            alert(e.length?e.join('\n'):'No failed rows recorded.');

            });

        });
     function initPage() {
        loadMappingPage();
        loadProgressPage();
        if($('#srwai-delete-type').length)loadDeleteCount();

        }
     function loadDeleteCount() {
        var type=$('#srwai-delete-type').val();
        if(!type)return;
        $('#srwai-delete-count').removeClass('alert-danger alert-success').addClass('alert-info').text('Loading post count...');
        $.post(SRWAI.ajaxurl, {
            action:'srwai_delete_count', nonce:SRWAI.nonce, post_type:type
            }, function(r) {
            if(!r.success) {
                $('#srwai-delete-count').removeClass('alert-info').addClass('alert-danger').text(r.data);
                return;

                }
            $('#srwai-delete-count').removeClass('alert-info').addClass('alert-success').html('<strong>Total '+esc(r.data.total)+' post'+(r.data.total===1?'':'s')+' found.</strong>');
            var q=parseInt($('#srwai-delete-quantity').val(), 10)||1;
            $('#srwai-delete-quantity').attr('max', r.data.total>0?r.data.total:1);
            if(q>r.data.total&&r.data.total>0)$('#srwai-delete-quantity').val(r.data.total);

            });

        }
     $(document).off('change.srwaiDelete', '#srwai-delete-type').on('change.srwaiDelete', '#srwai-delete-type', loadDeleteCount);
     $(document).off('click.srwaiDelete', '#srwai-delete-start').on('click.srwaiDelete', '#srwai-delete-start', function() {
        var b=$(this), type=$('#srwai-delete-type').val(), qty=parseInt($('#srwai-delete-quantity').val(), 10)||0, direction=$('#srwai-delete-direction').val(), images=$('#srwai-delete-images').is(':checked');
        if(qty<1) {
            alert('Please enter how many posts you want to delete.');
            return;

            }
        var directionText=direction==='new'?'newest':'oldest';
        if(!confirm('Delete '+qty+' '+directionText+' post(s) permanently?'))return;
        b.prop('disabled', true);
        $('#srwai-delete-result').html('<div class="alert alert-info">Deleting '+qty+' '+directionText+' post(s)...</div>');
        var remaining=qty;
        function go() {
            if(remaining<=0) {
                loadDeleteCount();
                $('#srwai-delete-result').append('<div class="alert alert-success mt-3">Deletion complete.</div>');
                b.prop('disabled', false);
                return;

                }
            var chunk=Math.min(25, remaining);
            $.post(SRWAI.ajaxurl, {
                action:'srwai_delete_batch', nonce:SRWAI.nonce, post_type:type, quantity:chunk, direction:direction, delete_images:images?1:0
                }, function(r) {
                if(!r.success) {
                    $('#srwai-delete-result').html('<div class="alert alert-danger">'+esc(r.data)+'</div>');
                    b.prop('disabled', false);
                    return;

                    }
                remaining-=parseInt(r.data.deleted, 10)||0;
                $('#srwai-delete-result').html('<div class="alert alert-info">Deleted '+(qty-remaining)+' of '+qty+'. Remaining posts: '+r.data.remaining_total+'.</div>');
                if(!r.data.deleted||remaining<=0) {
                    loadDeleteCount();
                    $('#srwai-delete-result').append('<div class="alert alert-success mt-3">Deletion complete.</div>');
                    b.prop('disabled', false);

                    }
                else go();

                }).fail(function() {
                b.prop('disabled', false);
                $('#srwai-delete-result').html('<div class="alert alert-danger">Delete request failed. Check debug.log.</div>');

                });

            }
        go();

        });
     window.addEventListener('popstate', function() {
        window.location.reload();

        });
     initPage();
     });
