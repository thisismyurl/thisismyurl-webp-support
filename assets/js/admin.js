/* global jQuery, window */
jQuery( function ( $ ) {
    'use strict';

    var config     = window.TIMUWebPSupportData || {};
    var pendingIds = Array.isArray( config.pendingIds ) ? config.pendingIds.slice() : [];
    var strings    = config.strings  || {};
    var actions    = config.actions  || {};
    var nonce      = config.nonce    || '';
    var ajaxUrl    = config.ajaxUrl  || window.ajaxurl;
    var batchSize  = Math.max( 1, parseInt( config.batchSize, 10 ) || 10 );
    var perPage    = Math.max( 5, parseInt( config.perPage,    10 ) || 25 );

    var completed   = 0;
    var isCancelled = false;
    var displayCompleted = 0;
    var spinFrames = [ '|', '/', '-', '\\' ];
    var spinIndex  = 0;
    var spinTimer  = null;

    /* ── Helpers ──────────────────────────────────────────────── */

    function postJson( payload ) {
        return $.ajax( {
            url:      ajaxUrl,
            method:   'POST',
            data:     payload,
            dataType: 'json'
        } );
    }

    function updateProgress( total ) {
        var shown = Math.min( total, Math.max( 0, displayCompleted ) );
        var pct = total > 0 ? Math.round( ( shown / total ) * 100 ) : 100;
        $( '#fwo-progress-bar' ).css( 'width', pct + '%' );
        $( '#fwo-progress-text' ).text( pct + '% (' + Math.round( shown ) + '/' + total + ')' );
        $( '#p-cnt' ).text( Math.max( 0, total - Math.round( shown ) ) );
    }

    function ensureBusyIndicator() {
        if ( $( '#fwo-active-spinner' ).length ) { return; }
        $( '.fwo-controls' ).append(
            '<span id="fwo-active-spinner" style="display:none;align-items:center;gap:8px;color:#2271b1;font-weight:600;">' +
            '<span class="spinner is-active" style="float:none;margin:0;"></span>' +
            '<span id="fwo-active-text">Working...</span>' +
            '</span>'
        );
    }

    function startBusyIndicator() {
        ensureBusyIndicator();
        $( '#fwo-active-spinner' ).css( 'display', 'inline-flex' );
        if ( spinTimer ) { return; }
        spinTimer = window.setInterval( function () {
            spinIndex = ( spinIndex + 1 ) % spinFrames.length;
            $( '#fwo-active-text' ).text( 'Working ' + spinFrames[ spinIndex ] );
        }, 120 );
    }

    function stopBusyIndicator() {
        if ( spinTimer ) {
            window.clearInterval( spinTimer );
            spinTimer = null;
        }
        $( '#fwo-active-spinner' ).hide();
    }

    function removeFromQueue( ids ) {
        if ( ! Array.isArray( ids ) || ! ids.length ) { return; }
        pendingIds = pendingIds.filter( function ( id ) {
            return ids.indexOf( id ) === -1;
        } );
    }

    /* ── Table: search + pagination ─────────────────────────── */

    /**
     * Attach a search bar and pagination controls to a widefat table.
     *
     * @param  {string} tableId   HTML id of the <table> element.
     * @param  {number} pp        Rows per page.
     * @return {{ refresh: Function }|null}
     */
    function initTable( tableId, pp ) {
        var $table = $( '#' + tableId );
        if ( ! $table.length ) { return null; }

        var $tbody  = $table.find( 'tbody' );
        var $wrap   = $table.closest( '.inside' );
        var colSpan = $table.find( 'thead th' ).length || 4;
        var page    = 1;
        var query   = '';

        /* Search bar */
        var $bar    = $( '<div>', { style: 'display:flex;gap:8px;align-items:center;margin-bottom:10px;' } );
        var $search = $( '<input>', {
            type:        'search',
            placeholder: 'Search by filename\u2026',
            'class':     'regular-text',
            style:       'max-width:260px;'
        } );
        var $count  = $( '<span>', { 'class': 'description', style: 'white-space:nowrap;' } );
        $bar.append( $search ).append( $count );
        $wrap.prepend( $bar );

        /* Pagination */
        var $pager = $( '<div>', { 'class': 'timu-pager', style: 'margin-top:10px;display:flex;gap:4px;align-items:center;flex-wrap:wrap;' } );
        $wrap.append( $pager );

        function render() {
            var $rows = $tbody.find( 'tr:not(.no-images):not(.timu-nores)' );

            /* Filter by search query */
            $rows.each( function () {
                $( this ).toggle( ! query || $( this ).text().toLowerCase().indexOf( query ) !== -1 );
            } );

            var $vis   = $rows.filter( ':visible' );
            var total  = $vis.length;
            var pages  = Math.max( 1, Math.ceil( total / pp ) );
            page       = Math.min( page, pages );

            /* Show only the current page slice */
            var start = ( page - 1 ) * pp;
            var end   = start + pp;
            $vis.each( function ( i ) {
                $( this ).toggle( i >= start && i < end );
            } );

            /* Empty-state placeholder */
            $tbody.find( '.timu-nores' ).remove();
            if ( total === 0 && ! $tbody.find( 'tr.no-images' ).is( ':visible' ) ) {
                $tbody.append(
                    '<tr class="timu-nores"><td colspan="' + colSpan + '" style="text-align:center;padding:12px 0;color:#646970;">' +
                    ( query ? 'No results match your search.' : 'Nothing here yet.' ) +
                    '</td></tr>'
                );
            }

            /* Result count label */
            $count.text( query ? total + ' result' + ( 1 !== total ? 's' : '' ) : '' );

            /* Pagination controls */
            $pager.empty();
            if ( pages <= 1 ) { return; }

            /* Prev */
            var $prev = $( '<button>', { type: 'button', text: '\u2039', 'class': 'button', style: 'min-width:32px;' } );
            $prev.prop( 'disabled', 1 === page );
            $prev.on( 'click', function () { if ( page > 1 ) { page--; render(); } } );
            $pager.append( $prev );

            /* Page number buttons with ellipsis */
            var last = 0;
            for ( var p = 1; p <= pages; p++ ) {
                if ( p === 1 || p === pages || ( p >= page - 2 && p <= page + 2 ) ) {
                    if ( last && p - last > 1 ) {
                        $pager.append( $( '<span>', { text: '\u2026', style: 'padding:0 4px;line-height:28px;' } ) );
                    }
                    /* Wrap loop var in IIFE to capture correct value */
                    ( function ( pg ) {
                        var $btn = $( '<button>', {
                            type:    'button',
                            text:    pg,
                            'class': 'button' + ( pg === page ? ' button-primary' : '' ),
                            style:   'min-width:32px;'
                        } );
                        $btn.on( 'click', function () { page = pg; render(); } );
                        $pager.append( $btn );
                    }( p ) );
                    last = p;
                }
            }

            /* Next */
            var $next = $( '<button>', { type: 'button', text: '\u203a', 'class': 'button', style: 'min-width:32px;' } );
            $next.prop( 'disabled', page === pages );
            $next.on( 'click', function () { if ( page < pages ) { page++; render(); } } );
            $pager.append( $next );

            $pager.append( $( '<span>', {
                text:  'Page ' + page + ' of ' + pages + ' \u2014 ' + total + ' items',
                style: 'margin-left:8px;color:#646970;font-size:12px;'
            } ) );
        }

        $search.on( 'input', function () {
            query = $( this ).val().toLowerCase().trim();
            page  = 1;
            render();
        } );

        render();
        return { refresh: render };
    }

    /* ── Restore buttons ─────────────────────────────────────── */

    $( document ).on( 'click', '.restore-btn', function () {
        var $btn = $( this );
        $btn.prop( 'disabled', true ).text( '\u2026' );
        postJson( { action: actions.restore, attachment_id: $btn.data( 'id' ), nonce: nonce } )
            .always( function () { window.location.reload(); } );
    } );

    $( '#btn-restore-all' ).on( 'click', function () {
        var ids = ( $( this ).data( 'ids' ) || [] ).slice();
        if ( ! window.confirm( strings.confirmRestoreAll || 'Restore all images?' ) ) { return; }
        $( this ).prop( 'disabled', true ).text( strings.restoring || 'Restoring\u2026' );
        var next = function () {
            if ( ! ids.length ) { window.location.reload(); return; }
            postJson( { action: actions.restore, attachment_id: ids.shift(), nonce: nonce } ).always( next );
        };
        next();
    } );

    /* ── Batch optimize ─────────────────────────────────────── */

    var pendingTable = initTable( 'fwo-pending-table', perPage );
    initTable( 'fwo-media-table', perPage );

    $( '#btn-start' ).on( 'click', function () {
        var $btn  = $( this );
        var total = pendingIds.length;
        if ( ! total ) { return; }

        displayCompleted = completed;
        updateProgress( total );

        $btn.prop( 'disabled', true ).text( strings.processing || 'Processing\u2026' );
        $( '#btn-cancel' ).show();
        $( '#fwo-progress-container' ).fadeIn();
        startBusyIndicator();

        var processNextBatch = function () {
            if ( isCancelled || ! pendingIds.length ) {
                stopBusyIndicator();
                return;
            }
            var batch = pendingIds.slice( 0, batchSize );
            var optimisticStart = completed;
            var optimisticEnd   = completed + batch.length;
            var optimisticTick  = null;
            var tickStart       = new Date().getTime();

            optimisticTick = window.setInterval( function () {
                var elapsed = new Date().getTime() - tickStart;
                var ratio   = Math.min( 0.95, elapsed / ( Math.max( 1, batch.length ) * 700 ) );
                displayCompleted = optimisticStart + ( ( optimisticEnd - optimisticStart ) * ratio );
                updateProgress( total );
            }, 180 );

            postJson( { action: actions.batch, attachment_ids: batch, nonce: nonce } )
                .done( function ( res ) {
                    if ( typeof res === 'string' ) {
                        try {
                            res = JSON.parse( res );
                        } catch ( e ) {
                            res = null;
                        }
                    }

                    if ( ! res || ! res.success || ! res.data ) {
                        isCancelled = true;
                        window.alert( 'Batch response was invalid. Processing paused to avoid losing queue state.' );
                        return;
                    }

                    var processed = Array.isArray( res.data.processed_ids ) ? res.data.processed_ids : [];
                    var failed    = Array.isArray( res.data.failed_ids )    ? res.data.failed_ids    : [];
                    var errors    = Array.isArray( res.data.errors )        ? res.data.errors        : [];

                    removeFromQueue( processed.concat( failed ) );

                    processed.forEach( function ( id ) { completed++; $( '#fwo-row-' + id ).remove(); } );
                    failed.forEach( function ( id ) {
                        var $row = $( '#fwo-row-' + id );
                        if ( $row.length ) {
                            $row.addClass( 'timu-failed-row' );
                            if ( ! $row.find( '.timu-failed-msg' ).length ) {
                                $row.find( 'td:last' ).append( '<div class="timu-failed-msg" style="color:#d63638;font-size:11px;margin-top:4px;">Failed in this run</div>' );
                            }
                        }
                    } );

                    if ( errors.length ) {
                        window.alert( ( strings.failedPrefix || 'Some images failed:' ) + '\n- ' + errors.join( '\n- ' ) );
                    }

                    displayCompleted = completed;
                    updateProgress( total );
                    if ( pendingTable ) { pendingTable.refresh(); }

                    if ( ! pendingIds.length ) {
                        stopBusyIndicator();
                        window.location.reload();
                        return;
                    }
                } )
                .fail( function () {
                    isCancelled = true;
                    window.alert( 'Batch request failed or timed out. Queue was preserved, so refresh and retry will continue from the same items.' );
                } )
                .always( function () {
                    if ( optimisticTick ) {
                        window.clearInterval( optimisticTick );
                        optimisticTick = null;
                    }
                    displayCompleted = completed;
                    updateProgress( total );
                    processNextBatch();
                } );
        };

        processNextBatch();
    } );

    $( '#btn-cancel' ).on( 'click', function () {
        isCancelled = true;
        stopBusyIndicator();
        window.location.reload();
    } );

    /* ── Theme image batch conversion ───────────────────────── */

    var themePaths = Array.isArray( config.themeImagePaths ) ? config.themeImagePaths.slice() : [];
    var themeActions = config.themeActions || {};
    var themeTable = initTable( 'fwo-theme-table', perPage );

    function cssEscapeValue( value ) {
        if ( window.CSS && typeof window.CSS.escape === 'function' ) {
            return window.CSS.escape( value );
        }

        return String( value ).replace( /["\\]/g, '\\$&' );
    }

    function findThemeRowByPath( path ) {
        var escaped = cssEscapeValue( path );
        return $( '#fwo-theme-table tbody tr[data-theme-path="' + escaped + '"]' );
    }

    $( '#btn-theme-start' ).on( 'click', function () {
        var $btn = $( this );
        var total = themePaths.length;

        if ( ! total ) { return; }

        $btn.prop( 'disabled', true ).text( strings.processing || 'Processing\u2026' );

        var convertedTotal = 0;

        var processThemeBatch = function () {
            if ( ! themePaths.length ) {
                $( '#ti-status' ).text( 'Complete' );
                $( '#ti-cnt' ).text( '0' );
                if ( themeTable ) { themeTable.refresh(); }
                return;
            }

            var batch = themePaths.slice( 0, batchSize );

            postJson( {
                action: themeActions.convertThemeBatch || 'timu_wsconvert_theme_batch',
                paths: batch,
                nonce: nonce
            } )
                .done( function ( res ) {
                    if ( typeof res === 'string' ) {
                        try {
                            res = JSON.parse( res );
                        } catch ( e ) {
                            res = null;
                        }
                    }

                    if ( ! res || ! res.success || ! res.data ) {
                        window.alert( 'Theme batch response was invalid. Please refresh and retry.' );
                        return;
                    }

                    var converted = Array.isArray( res.data.converted ) ? res.data.converted : [];
                    var failed = Array.isArray( res.data.failed ) ? res.data.failed : [];
                    var processed = converted.concat( failed );

                    themePaths = themePaths.filter( function ( path ) {
                        return processed.indexOf( path ) === -1;
                    } );

                    converted.forEach( function ( path ) {
                        convertedTotal++;
                        var $row = findThemeRowByPath( path );
                        if ( $row.length ) {
                            $row.find( 'td:last' ).html( '<span style="color:#00a32a;">&#10003; Converted</span>' );
                        }
                    } );

                    failed.forEach( function ( path ) {
                        var $row = findThemeRowByPath( path );
                        if ( $row.length ) {
                            $row.find( 'td:last' ).html( '<span style="color:#d63638;">Failed</span>' );
                        }
                    } );

                    $( '#ti-cnt' ).text( String( themePaths.length ) );
                    $( '#ti-status' ).text( convertedTotal + ' / ' + total + ' converted' );

                    if ( themeTable ) { themeTable.refresh(); }
                } )
                .fail( function () {
                    window.alert( 'Theme batch request failed. Please refresh and retry.' );
                } )
                .always( function () {
                    if ( themePaths.length ) {
                        processThemeBatch();
                    }
                } );
        };

        processThemeBatch();
    } );
} );
