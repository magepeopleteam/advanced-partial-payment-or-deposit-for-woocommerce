( function () {
    'use strict';

    var namespace = 'advanced-partial-payment';
    var summaryClass = 'apd-blocks-summary';
    var labels = window.apd_blocks || {};
    var rafId = null;
    var lastRender = 0;
    var RENDER_INTERVAL = 150; // ms — debounce re-renders
    var toggleState = 'deposit'; // 'deposit' | 'full' — local state for toggle
    var isToggling = false;      // prevent double-clicks

    /**
     * Trigger a WooCommerce cart refresh via the data store.
     */
    var refreshCart = function () {
        if (
            window.wp &&
            window.wp.data &&
            typeof window.wp.data.dispatch === 'function'
        ) {
            try {
                var store = window.wp.data.dispatch( 'wc/store/cart' );
                if ( store && typeof store.invalidateResolutionForStore === 'function' ) {
                    store.invalidateResolutionForStore();
                }
            } catch ( e ) {
                // Fallback: reload page if data store refresh fails.
                window.location.reload();
            }
        }
    };

    /**
     * Call AJAX to update payment type for all cart items.
     */
    var updatePaymentTypeAll = function ( paymentType ) {
        if ( isToggling || ! labels.ajax_url ) {
            return Promise.resolve( false );
        }

        isToggling = true;

        var formData = new window.FormData();
        formData.append( 'action', 'apd_update_cart_payment_type_all' );
        formData.append( 'nonce', labels.nonce || '' );
        formData.append( 'payment_type', paymentType );

        return window.fetch( labels.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        } )
        .then( function ( response ) { return response.json(); } )
        .then( function ( result ) {
            if ( result && result.success ) {
                toggleState = paymentType;
                refreshCart();
                return true;
            }
            return false;
        } )
        .catch( function () {
            return false;
        } )
        .finally( function () {
            isToggling = false;
        } );
    };

    /**
     * Safely retrieve extension data from checkout filter args.
     */
    var getExtensionData = function ( extensions, args ) {
        if ( extensions && extensions[ namespace ] ) {
            return extensions[ namespace ];
        }

        if (
            args &&
            args.cart &&
            args.cart.extensions &&
            args.cart.extensions[ namespace ]
        ) {
            return args.cart.extensions[ namespace ];
        }

        return null;
    };

    /**
     * Get the WooCommerce cart data store.
     */
    var getCartData = function () {
        if (
            ! window.wp ||
            ! window.wp.data ||
            typeof window.wp.data.select !== 'function'
        ) {
            return null;
        }

        try {
            var store = window.wp.data.select( 'wc/store/cart' );

            if ( ! store || typeof store.getCartData !== 'function' ) {
                return null;
            }

            return store.getCartData();
        } catch ( error ) {
            return null;
        }
    };

    /**
     * Get deposit summary from cart extensions.
     */
    var getSummaryData = function () {
        var cartData = getCartData();

        if (
            ! cartData ||
            ! cartData.extensions ||
            ! cartData.extensions[ namespace ]
        ) {
            return null;
        }

        return cartData.extensions[ namespace ];
    };

    /**
     * Get cart items array.
     */
    var getCartItems = function () {
        var cartData = getCartData();

        if ( ! cartData || ! Array.isArray( cartData.items ) ) {
            return [];
        }

        return cartData.items;
    };

    /**
     * Escape HTML entities.
     */
    var escapeHtml = function ( value ) {
        if ( ! value ) {
            return '';
        }
        return String( value )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    };

    /**
     * Find all totals footer items in the current DOM.
     */
    var getFooterItems = function () {
        var items = document.querySelectorAll( '.wc-block-components-totals-footer-item' );
        return items ? Array.prototype.slice.call( items ) : [];
    };

    /**
     * Fallback: attempt to detect deposit state from existing DOM rows.
     * Used when the Store API extension data is not yet available.
     */
    var getFallbackSummaryData = function ( footerItem ) {
        if ( ! footerItem ) {
            return null;
        }

        var totalValueNode = footerItem.querySelector(
            '.wc-block-components-totals-item__value'
        );

        if ( ! totalValueNode ) {
            return null;
        }

        var feeLabels = document.querySelectorAll(
            '.wc-block-components-totals-item__label, .wc-block-formatted-money-amount + span'
        );
        var hasPayLaterRow = false;

        if ( feeLabels && feeLabels.length ) {
            Array.prototype.forEach.call( feeLabels, function ( node ) {
                if ( ! hasPayLaterRow && node.textContent ) {
                    if ( /pay later/i.test( node.textContent ) ) {
                        hasPayLaterRow = true;
                    }
                }
            } );
        }

        if ( ! hasPayLaterRow ) {
            return null;
        }

        return {
            has_deposit: true,
            deposit_total_label:
                ( labels.deposit_label || 'Deposit' ) +
                ' (' +
                ( labels.to_pay_now || 'To Pay Now' ) +
                ')',
            deposit_amount_html: totalValueNode.innerHTML,
            deposit_description: labels.to_pay_now || 'To Pay Now',
        };
    };

    /**
     * Build a single summary row HTML string.
     */
    var createSummaryRowHtml = function ( label, valueHtml, rowClass ) {
        return (
            '<div class="wc-block-components-totals-item ' +
            summaryClass +
            '__row ' +
            rowClass +
            '">' +
                '<div class="apd-blocks-summary__label-wrap">' +
                    '<span class="wc-block-components-totals-item__label">' +
                        escapeHtml( label ) +
                    '</span>' +
                '</div>' +
                '<span class="wc-block-components-totals-item__value">' +
                    ( valueHtml || '' ) +
                '</span>' +
            '</div>'
        );
    };

    /**
     * Render a deposit / full payment toggle in cart/checkout totals.
     */
    var renderDepositToggle = function () {
        var cartData = getCartData();
        var summaryData = cartData && cartData.extensions
            ? cartData.extensions[ namespace ]
            : null;

        if ( ! summaryData || ! summaryData.has_deposit ) {
            return;
        }

        var depositLabel = labels.strings && labels.strings.pay_deposit
            ? labels.strings.pay_deposit
            : 'Pay Deposit';
        var fullLabel = labels.strings && labels.strings.pay_full
            ? labels.strings.pay_full
            : 'Pay Full Amount';

        var containerSelectors = [
            '.wc-block-cart__payment-options',
            '.wc-block-checkout__payment-method',
            '.wc-block-components-totals-wrapper',
        ];

        var container = null;
        for ( var i = 0; i < containerSelectors.length; i++ ) {
            container = document.querySelector( containerSelectors[ i ] );
            if ( container ) {
                break;
            }
        }

        if ( ! container ) {
            return;
        }

        var existing = container.querySelector( '.apd-deposit-toggle' );
        if ( existing ) {
            return; // Already rendered.
        }

        var wrapper = document.createElement( 'div' );
        wrapper.className = 'apd-deposit-toggle wc-block-components-totals-item';
        wrapper.style.marginTop = '12px';
        wrapper.style.marginBottom = '12px';
        wrapper.style.padding = '12px';
        wrapper.style.border = '1px dashed #c3c4c7';
        wrapper.style.borderRadius = '4px';

        var currentLabel = toggleState === 'deposit' ? depositLabel : fullLabel;
        var nextLabel    = toggleState === 'deposit' ? fullLabel : depositLabel;
        var nextState    = toggleState === 'deposit' ? 'full' : 'deposit';

        wrapper.innerHTML =
            '<div class="apd-deposit-toggle__label" style="font-weight:600;margin-bottom:8px;">' +
                escapeHtml( currentLabel ) +
            '</div>' +
            '<button type="button" class="apd-deposit-toggle__btn wc-block-components-button wp-element-button" ' +
                'style="font-size:14px;padding:8px 16px;cursor:pointer;">' +
                escapeHtml( nextLabel ) +
            '</button>';

        var btn = wrapper.querySelector( '.apd-deposit-toggle__btn' );
        if ( btn ) {
            btn.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                e.stopPropagation();

                btn.disabled = true;
                btn.textContent = '...';

                updatePaymentTypeAll( nextState ).then( function ( ok ) {
                    if ( ! ok ) {
                        btn.disabled = false;
                        btn.textContent = nextLabel;
                    }
                    // On success the cart refresh will re-render everything.
                } );
            } );
        }

        container.insertBefore( wrapper, container.firstChild );
    };

    /**
     * Render deposit summary rows in cart/checkout totals.
     */
    var renderSummary = function () {
        var footerItems = getFooterItems();

        if ( ! footerItems.length ) {
            return;
        }

        var data = getSummaryData();

        footerItems.forEach( function ( footerItem ) {
            var container = footerItem.parentElement;
            var existing = container
                ? container.querySelector( '.' + summaryClass )
                : null;
            var summaryData = data;

            if ( ! summaryData || ! summaryData.has_deposit ) {
                summaryData = getFallbackSummaryData( footerItem );
            }

            if ( ! container || ! summaryData || ! summaryData.has_deposit ) {
                if ( existing ) {
                    existing.remove();
                }
                return;
            }

            var summaryHtml = createSummaryRowHtml(
                summaryData.deposit_total_label ||
                    (
                        ( labels.deposit_label || 'Deposit' ) +
                        ' (' +
                        ( labels.to_pay_now || 'To Pay Now' ) +
                        ')'
                    ),
                summaryData.deposit_amount_html || '',
                summaryClass + '__row--deposit'
            );

            var summary = existing || document.createElement( 'div' );
            summary.className = summaryClass;

            if ( summary.innerHTML !== summaryHtml ) {
                summary.innerHTML = summaryHtml;
            }

            if (
                summary.parentNode !== container ||
                summary.nextElementSibling !== footerItem
            ) {
                container.insertBefore( summary, footerItem );
            }
        } );
    };

    /**
     * Render deposit badges next to individual cart line items.
     */
    var renderItemDeposits = function () {
        var items = getCartItems();

        if ( ! items.length ) {
            return;
        }

        var productNames = document.querySelectorAll(
            '.wc-block-cart-items .wc-block-components-product-name, .wc-block-checkout__sidebar .wc-block-components-product-name'
        );

        if ( ! productNames || ! productNames.length ) {
            return;
        }

        Array.prototype.forEach.call( productNames, function ( node, index ) {
            var item = items[ index ];
            var itemExtensions =
                item && item.extensions && item.extensions[ namespace ]
                    ? item.extensions[ namespace ]
                    : null;
            var parent = node.parentElement;
            var existing = parent
                ? parent.querySelector( '.apd-blocks-item-deposit' )
                : null;

            if (
                ! itemExtensions ||
                ! itemExtensions.has_deposit ||
                ! itemExtensions.deposit_amount_html
            ) {
                if ( existing ) {
                    existing.remove();
                }
                return;
            }

            if ( ! parent ) {
                return;
            }

            var badge = existing || document.createElement( 'div' );
            badge.className = 'apd-blocks-item-deposit';
            badge.innerHTML =
                '<small class="apd-cart-deposit-tag">' +
                    escapeHtml(
                        itemExtensions.deposit_label ||
                        labels.deposit_label ||
                        'Deposit'
                    ) +
                    ': ' +
                    itemExtensions.deposit_amount_html +
                '</small>';

            if ( ! existing ) {
                node.insertAdjacentElement( 'afterend', badge );
            }
        } );
    };

    /**
     * Register checkout filter hooks to override total labels.
     */
    var registerTotalFilters = function () {
        if (
            ! window.wc ||
            ! window.wc.blocksCheckout ||
            ! window.wc.blocksCheckout.registerCheckoutFilters
        ) {
            return;
        }

        var registerCheckoutFilters = window.wc.blocksCheckout.registerCheckoutFilters;

        var updateTotalLabel = function ( defaultValue, extensions, args ) {
            var data = getExtensionData( extensions, args );

            if ( ! data || ! data.has_deposit || ! data.deposit_total_label ) {
                return defaultValue;
            }

            return data.deposit_total_label;
        };

        var updateTotalValue = function ( defaultValue, extensions, args, validation ) {
            var data = getExtensionData( extensions, args );

            if ( ! data || ! data.has_deposit || ! data.total_value_label ) {
                return defaultValue;
            }

            if ( validation && data.total_value_label.indexOf( '<price/>' ) === -1 ) {
                return defaultValue;
            }

            return data.total_value_label;
        };

        registerCheckoutFilters( namespace, {
            totalLabel: updateTotalLabel,
            totalValue: updateTotalValue,
        } );
    };

    /**
     * Debounced render — prevents excessive DOM thrashing.
     */
    var scheduleRender = function () {
        var now = Date.now();

        if ( now - lastRender < RENDER_INTERVAL ) {
            if ( rafId ) {
                cancelAnimationFrame( rafId );
            }
            rafId = requestAnimationFrame( function () {
                setTimeout( scheduleRender, RENDER_INTERVAL );
            } );
            return;
        }

        lastRender = now;
        rafId = null;

        try {
            renderSummary();
            renderItemDeposits();
            renderDepositToggle();
        } catch ( e ) {
            // Silently fail to avoid breaking checkout.
            if ( window.console && window.console.error ) {
                window.console.error( 'APD Blocks render error:', e );
            }
        }
    };

    // Register checkout filter hooks immediately.
    registerTotalFilters();

    // Initial render.
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', scheduleRender );
    } else {
        scheduleRender();
    }

    // Subscribe to WooCommerce data store changes.
    if (
        window.wp &&
        window.wp.data &&
        typeof window.wp.data.subscribe === 'function'
    ) {
        window.wp.data.subscribe( scheduleRender );
    }

    // Lightweight MutationObserver — only watch cart/checkout containers.
    if ( typeof window.MutationObserver === 'function' ) {
        var observerContainers = document.querySelectorAll(
            '.wc-block-cart, .wc-block-checkout, .wc-block-components-sidebar'
        );

        if ( observerContainers && observerContainers.length ) {
            var observer = new window.MutationObserver( function () {
                scheduleRender();
            } );

            Array.prototype.forEach.call( observerContainers, function ( container ) {
                observer.observe( container, {
                    childList: true,
                    subtree: true,
                } );
            } );
        }
    }
} )();
