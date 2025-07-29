/**
 * AboveWP Bulgarian Eurozone - JavaScript for WooCommerce Blocks
 * 
 * Handles dual currency display (BGN/EUR) for WooCommerce Gutenberg blocks
 * with configurable EUR price positioning (left or right of BGN price)
 */
(function($) {
    'use strict';

    // Ensure we have the conversion data
    if (typeof abovewpBGE === 'undefined') {
        console.error('AboveWP Bulgarian Eurozone: Missing conversion data');
        return;
    }
    
    // Get conversion rate, EUR label, position, and format from localized data
    const conversionRate = abovewpBGE.conversionRate;
    const eurLabel = abovewpBGE.eurLabel;
    const eurPosition = abovewpBGE.eurPosition || 'right';
    const eurFormat = abovewpBGE.eurFormat || 'brackets';

    /**
     * Convert BGN to EUR
     * 
     * @param {string} bgnPrice - Price in BGN (may include thousands separators)
     * @return {string} - Formatted price in EUR with 2 decimal places
     */
    function convertBgnToEur(bgnPrice) {
        // Remove all thousands separators (spaces and dots) and normalize decimal separator
        let normalizedPrice = String(bgnPrice);
        
        // Check if the last comma/dot is the decimal separator (2 digits after it)
        const decimalMatch = normalizedPrice.match(/[.,](\d{2})$/);
        
        if (decimalMatch) {
            // Split at the decimal separator
            const decimalPart = decimalMatch[1];
            const integerPart = normalizedPrice.substring(0, normalizedPrice.length - 3);
            
            // Remove all spaces and dots from integer part (thousands separators)
            const cleanIntegerPart = integerPart.replace(/[\s.]/g, '');
            
            // Reconstruct with dot as decimal separator
            normalizedPrice = cleanIntegerPart + '.' + decimalPart;
        } else {
            // No decimal part, just remove thousands separators
            normalizedPrice = normalizedPrice.replace(/[\s.,]/g, '');
        }
        
        // Convert to EUR
        return (parseFloat(normalizedPrice) / conversionRate).toFixed(2);
    }

    /**
     * Format EUR price with label
     * 
     * @param {number|string} eurPrice - Price in EUR
     * @return {string} - Formatted price with EUR label
     */
    function formatEurPrice(eurPrice) {
        if (eurFormat === 'divider') {
            return '/ ' + eurPrice + ' ' + eurLabel;
        } else {
            return '(' + eurPrice + ' ' + eurLabel + ')';
        }
    }

    /**
     * Format dual currency price based on position setting
     * 
     * @param {string} bgnPriceHtml - The original BGN price HTML/text
     * @param {number|string} eurPrice - The EUR price amount
     * @return {string} - The formatted dual currency price
     */
    function formatDualPrice(bgnPriceHtml, eurPrice) {
        const eurFormatted = formatEurPrice(eurPrice);
        const eurSpan = '<span class="eur-price">' + eurFormatted + '</span>';
        
        if (eurPosition === 'left') {
            return eurSpan + ' ' + bgnPriceHtml;
        } else {
            return bgnPriceHtml + ' ' + eurSpan;
        }
    }
    
    /**
     * Check if element already has a EUR price
     * 
     * @param {Element} element - The element to check
     * @return {boolean} - True if element already has EUR price
     */
    function hasEurPrice(element) {
        const $element = $(element);
        
        // Check for span with eur-price class within or next to the element
        if ($element.find('.eur-price').length > 0 || 
            $element.siblings('.eur-price').length > 0 ||
            $element.next('.eur-price').length > 0 ||
            $element.prev('.eur-price').length > 0) {
            return true;
        }
        
        // Check parent containers for EUR spans
        if ($element.parent().find('.eur-price').length > 0) {
            return true;
        }
        
        // For shipping methods, check the entire list item
        if ($element.closest('li').find('.eur-price').length > 0) {
            return true;
        }
        
        // Check if the text already contains EUR symbol (be more specific)
        const text = $element.text();
        if (text.includes('(' + eurLabel + ')') || text.includes(eurLabel + ')') || 
            text.includes('/ ' + eurLabel) || text.includes('/ ' + eurLabel + ')')) {
            return true;
        }
        
        // For mini-cart specifically
        if ($element.closest('.mini_cart_item').length > 0 && 
            $element.closest('.mini_cart_item').find('.eur-price').length > 0) {
            return true;
        }
        
        return false;
    }

    /**
     * Add EUR price to a price element based on position setting
     * 
     * @param {Element} element - The element containing the price
     */
    function addEurPrice(element) {
        // Skip if already processed
        if (hasEurPrice(element)) {
            return;
        }
        
        const $element = $(element);
        const text = $element.text().trim();
        
        // Match BGN price pattern with thousands separators
        // Examples: "1 650,00 лв.", "1.650,00 лв.", "25,00 лв.", "1650,00"
        const pricePattern = /(\d+(?:[\s.]\d{3})*[.,]\d{2})\s*(?:лв\.|BGN)?/;
        const match = text.match(pricePattern);
        
        if (match) {
            const priceBgn = match[1];
            const priceEur = convertBgnToEur(priceBgn);
            
            // Create the EUR price element
            const $eurSpan = $('<span class="eur-price">' + formatEurPrice(priceEur) + '</span>');
            
            // Add based on position setting
            if (eurPosition === 'left') {
                $element.prepend($eurSpan).prepend(' ');
            } else {
                $element.append(' ').append($eurSpan);
            }
        }
    }

    /**
     * Replace element content with dual currency price based on position setting
     * 
     * @param {Element} element - The element containing the price
     */
    function replaceDualPrice(element) {
        // Skip if already processed
        if (hasEurPrice(element)) {
            return;
        }
        
        const $element = $(element);
        const originalHtml = $element.html();
        const text = $element.text().trim();
        
        // Match BGN price pattern with thousands separators
        // Examples: "1 650,00 лв.", "1.650,00 лв.", "25,00 лв.", "1650,00"
        const pricePattern = /(\d+(?:[\s.]\d{3})*[.,]\d{2})\s*(?:лв\.|BGN)?/;
        const match = text.match(pricePattern);
        
        if (match) {
            const priceBgn = match[1];
            const priceEur = convertBgnToEur(priceBgn);
            
            // Replace content with dual price
            const dualPriceHtml = formatDualPrice(originalHtml, priceEur);
            $element.html(dualPriceHtml);
        }
    }
    
    /**
     * Process cart item prices
     */
    function processCartItemPrices() {
        // Product prices in cart - use replaceDualPrice for cleaner positioning
        $('.wc-block-components-product-price').each(function() {
            // Check if this is a price range (contains dash)
            if ($(this).text().includes('–') || $(this).text().includes('-')) {
                addEurPrice(this); // For ranges, append is safer
            } else {
                replaceDualPrice(this); // For single prices, replace for better positioning
            }
        });
        
        // Product total prices in cart
        $('.wc-block-cart-item__total-price-and-sale-badge-wrapper .wc-block-components-product-price').each(function() {
            replaceDualPrice(this);
        });
    }
    
    /**
     * Process cart totals
     */
    function processCartTotals() {
        // Subtotal - use replaceDualPrice for better positioning control
        $('.wc-block-components-totals-item__value').each(function() {
            replaceDualPrice(this);
        });
        
        // Footer total - use replaceDualPrice for better positioning control
        $('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value').each(function() {
            replaceDualPrice(this);
        });
    }
    


    /**
     * Process shipping methods in cart/checkout
     */
    function processShippingMethods() {
        // Process shipping methods in the shipping table
        $('#shipping_method li, .woocommerce-shipping-methods li').each(function() {
            var $li = $(this);
            
            // Check if the label already contains EUR information (skip these)
            var labelText = $li.find('label').text();
            if (labelText && (labelText.indexOf('€') !== -1 || labelText.indexOf('EUR') !== -1)) {
                return; // Skip if label already has EUR built in
            }
            
            // Find price spans within this shipping method
            var $priceSpan = $li.find('.woocommerce-Price-amount');
            if ($priceSpan.length > 0) {
                $priceSpan.each(function() {
                    var $this = $(this);
                    var text = $this.text().trim();
                    var html = $this.html(); // Get HTML to handle &nbsp; entities
                    
                    // Skip if no price or price already contains EUR text
                    if (!text || text.indexOf(eurLabel) !== -1) {
                        return;
                    }
                    
                    // Enhanced BGN price pattern to handle &nbsp; entities and various formats
                    // Handle both text and HTML content for better matching
                    var priceMatch = text.match(/(\d+(?:[,\s.]\d{3})*[,\.]\d{2})\s*(?:лв\.|BGN)?/) || 
                                   html.match(/(\d+(?:[,\s.&nbsp;]\d{3})*[,\.]\d{2})\s*(?:лв\.|BGN)?/);
                    
                    if (priceMatch) {
                        var priceBgnRaw = priceMatch[1];
                        // Clean up the price: remove &nbsp; entities, spaces, and normalize decimal separator
                        var priceBgn = priceBgnRaw.replace(/&nbsp;/g, '').replace(/\s/g, '').replace(',', '.');
                        var currentPriceEur = (parseFloat(priceBgn) / conversionRate).toFixed(2);
                        
                        // Check if there's already an EUR price for this shipping method
                        var $existingEurSpan = $li.find('.eur-price');
                        if ($existingEurSpan.length > 0) {
                            // Extract the existing EUR price
                            var existingEurText = $existingEurSpan.text();
                            var existingEurMatch = existingEurText.match(/(\d+[.,]\d{2})/);
                            
                            if (existingEurMatch) {
                                var existingEurPrice = existingEurMatch[1].replace(',', '.');
                                // If the EUR prices don't match (BGN price changed), remove old EUR
                                if (Math.abs(parseFloat(currentPriceEur) - parseFloat(existingEurPrice)) > 0.01) {
                                    $existingEurSpan.remove();
                                } else {
                                    // EUR price is correct, skip adding new one
                                    return;
                                }
                            } else {
                                // Can't parse existing EUR, remove it to be safe
                                $existingEurSpan.remove();
                            }
                        }
                        
                        // Add the new/updated EUR price
                        var eurFormatted = formatEurPrice(currentPriceEur);
                        var eurSpan = '<span class="eur-price">' + eurFormatted + '</span>';
                        
                        if (eurPosition === 'left') {
                            $this.before(eurSpan + ' ');
                        } else {
                            $this.after(' ' + eurSpan);
                        }
                    }
                });
            }
        });
    }

    /**
     * Process cart fees (like Cash on Delivery fees)
     */
    function processCartFees() {
        $('.fee .woocommerce-Price-amount').each(function() {
            if (!hasEurPrice(this)) {
                addEurPrice(this);
            }
        });
    }

    /**
     * Process shipping methods in WooCommerce Checkout Block
     */
    function processCheckoutBlockShipping() {
        // Handle shipping methods in the new WooCommerce checkout blocks
        $('.wc-block-components-radio-control__option').each(function() {
            var $option = $(this);
            
            // Skip if this shipping option already has EUR conversion
            if ($option.find('.eur-price').length > 0) {
                return;
            }
            
            // Find the price element within this shipping option
            var $priceElement = $option.find('.wc-block-formatted-money-amount.wc-block-components-formatted-money-amount');
            if ($priceElement.length > 0) {
                $priceElement.each(function() {
                    var $this = $(this);
                    var text = $this.text().trim();
                    
                    // Skip if no price, already has EUR, or is free shipping
                    if (!text || text.indexOf(eurLabel) !== -1 || text.toLowerCase().indexOf('безплатно') !== -1 || text.toLowerCase().indexOf('free') !== -1) {
                        return;
                    }
                    
                    // Match BGN price pattern
                    var priceMatch = text.match(/(\d+(?:[,\s.]\d{3})*[,]\d{2})\s*(?:лв\.|BGN)?/);
                    if (priceMatch) {
                        var priceBgn = priceMatch[1].replace(/\s/g, '').replace(',', '.');
                        var priceEur = (parseFloat(priceBgn) / conversionRate).toFixed(2);
                        var eurFormatted = formatEurPrice(priceEur);
                        var eurSpan = '<span class="eur-price">' + eurFormatted + '</span>';
                        
                        if (eurPosition === 'left') {
                            $this.before(eurSpan + ' ');
                        } else {
                            $this.after(' ' + eurSpan);
                        }
                    }
                });
            }
        });
        
        // Also handle shipping costs in block-based cart totals and order review
        $('.wc-block-components-totals-shipping .wc-block-formatted-money-amount, .wc-block-components-totals-item .wc-block-components-totals-item__value').each(function() {
            var $this = $(this);
            
            // For order review totals, check if this is a shipping-related item
            var $totalsItem = $this.closest('.wc-block-components-totals-item');
            if ($totalsItem.length > 0) {
                var labelText = $totalsItem.find('.wc-block-components-totals-item__label').text().toLowerCase();
                // Check if this is shipping-related (various language versions)
                if (labelText.indexOf('доставка') === -1 && 
                    labelText.indexOf('shipping') === -1 && 
                    labelText.indexOf('delivery') === -1) {
                    return; // Skip if not shipping-related
                }
            }
            
            if (!hasEurPrice(this)) {
                var text = $this.text().trim();
                
                if (text && text.indexOf(eurLabel) === -1 && 
                    text.toLowerCase().indexOf('безплатно') === -1 && 
                    text.toLowerCase().indexOf('free') === -1) {
                    var priceMatch = text.match(/(\d+(?:[,\s.]\d{3})*[,]\d{2})\s*(?:лв\.|BGN)?/);
                    if (priceMatch) {
                        var priceBgn = priceMatch[1].replace(/\s/g, '').replace(',', '.');
                        var priceEur = (parseFloat(priceBgn) / conversionRate).toFixed(2);
                        var eurFormatted = formatEurPrice(priceEur);
                        var eurSpan = '<span class="eur-price">' + eurFormatted + '</span>';
                        
                        if (eurPosition === 'left') {
                            $this.before(eurSpan + ' ');
                        } else {
                            $this.after(' ' + eurSpan);
                        }
                    }
                }
            }
        });
    }

    /**
     * Process all prices in cart/checkout blocks
     */
    function processAllPrices() {
        processCartItemPrices();
        processCartTotals();
        processShippingMethods();
        processCheckoutBlockShipping(); // Handle new WooCommerce checkout blocks
        processCartFees();
        
        // Also process regular mini cart items (non-block version)
        $('.widget_shopping_cart .mini_cart_item .quantity').each(function() {
            if (!hasEurPrice(this)) {
                addEurPrice(this);
            }
        });
    }
    
    /**
     * Initialize the script
     */
    function init() {
        // Process all prices initially
        processAllPrices();
        
        // Set up mutation observer to catch dynamic updates
        const observer = new MutationObserver(function(mutations) {
            var shouldProcess = false;
            
            mutations.forEach(function(mutation) {
                // Check if the mutation affects shipping methods or prices
                if (mutation.type === 'childList') {
                    // Check if shipping-related elements were added/removed
                    if (mutation.target.querySelector && (
                        mutation.target.querySelector('.woocommerce-Price-amount') ||
                        mutation.target.querySelector('#shipping_method') ||
                        mutation.target.querySelector('.wc-block-formatted-money-amount') ||
                        mutation.target.querySelector('.wc-block-components-radio-control__option') ||
                        mutation.target.id === 'shipping_method' ||
                        mutation.target.classList.contains('woocommerce-shipping-totals') ||
                        mutation.target.classList.contains('woocommerce-shipping-methods') ||
                        mutation.target.classList.contains('wc-block-components-shipping-rates-control') ||
                        mutation.target.classList.contains('wc-block-checkout__shipping-option') ||
                        mutation.target.classList.contains('wc-block-components-totals-item') ||
                        mutation.target.classList.contains('wc-block-components-totals-item__value')
                    )) {
                        shouldProcess = true;
                    }
                    
                    // Check if added nodes contain shipping method elements
                    if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                        for (var i = 0; i < mutation.addedNodes.length; i++) {
                            var node = mutation.addedNodes[i];
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                if (node.querySelector && (
                                    node.querySelector('.woocommerce-Price-amount') ||
                                    node.querySelector('#shipping_method') ||
                                    node.classList.contains('woocommerce-shipping-methods') ||
                                    node.id === 'shipping_method'
                                )) {
                                    shouldProcess = true;
                                    break;
                                }
                            }
                        }
                    }
                } else if (mutation.type === 'characterData') {
                    // Check if text content changed in price-related elements
                    var target = mutation.target.parentElement;
                    if (target && (
                        target.classList.contains('woocommerce-Price-amount') ||
                        target.querySelector('.woocommerce-Price-amount')
                    )) {
                        shouldProcess = true;
                    }
                }
            });
            
            if (shouldProcess) {
                // Small delay to allow for DOM updates to complete
                setTimeout(function() {
                    processAllPrices();
                    // Also run a second check after a bit more delay for stubborn updates
                    setTimeout(processAllPrices, 100);
                }, 50);
            }
        });
        
        // Observe cart/checkout container for changes
        const containers = document.querySelectorAll(
            '.wp-block-woocommerce-cart, ' + 
            '.wp-block-woocommerce-checkout, ' + 
            '.wp-block-woocommerce-mini-cart, ' +
            '.widget_shopping_cart, ' +
            '.woocommerce-shipping-totals, ' +
            '.woocommerce-shipping-methods, ' +
            '.wc-block-checkout__shipping-option, ' +
            '.wc-block-components-shipping-rates-control, ' +
            '.wc-block-components-totals-wrapper, ' +
            '.wc-block-components-totals-item, ' +
            '#shipping_method'
        );
        
        // Also observe the parent containers that might get replaced entirely
        const parentContainers = document.querySelectorAll(
            '.woocommerce-checkout-review-order-table, ' +
            '.woocommerce-checkout-review-order, ' +
            '.shop_table_responsive, ' +
            '.cart_totals'
        );
        
        for (const container of containers) {
            observer.observe(container, { 
                childList: true, 
                subtree: true,
                characterData: true,
                attributes: true,
                attributeFilter: ['class', 'data-title']
            });
        }
        
        // Observe parent containers with more focus on child changes
        for (const parentContainer of parentContainers) {
            observer.observe(parentContainer, { 
                childList: true, 
                subtree: true,
                characterData: true
            });
        }
        
        // Also observe the entire cart/checkout forms
        const forms = document.querySelectorAll('.woocommerce-cart-form, .woocommerce-checkout');
        for (const form of forms) {
            if (form) {
                observer.observe(form, { 
                    childList: true, 
                    subtree: true,
                    characterData: true
                });
            }
        }
        
        // Listen for WooCommerce block events
        $(document).on('wc-blocks-cart-update wc-blocks-checkout-update', function() {
            setTimeout(processAllPrices, 100);
        });
        
        // Listen for checkout block specific shipping updates
        $(document).on('change', '.wc-block-components-radio-control__input', function() {
            setTimeout(processAllPrices, 150);
        });
        
        // Handle quantity changes
        $(document).on('change', '.wc-block-components-quantity-selector__input', function() {
            setTimeout(processAllPrices, 100);
        });
        
        // Handle quantity button clicks
        $(document).on('click', '.wc-block-components-quantity-selector__button', function() {
            setTimeout(processAllPrices, 100);
        });
        
        // Handle mini cart events
        $(document).on('added_to_cart removed_from_cart updated_cart_totals', function() {
            setTimeout(processAllPrices, 100);
        });
        
        // Handle shipping method changes
        $(document).on('change', 'input[name^="shipping_method"]', function() {
            setTimeout(processAllPrices, 100);
        });
        
        // Handle checkout updates (including shipping method updates)
        $(document).on('updated_checkout', function() {
            setTimeout(processAllPrices, 150);
        });
        
        // Handle shipping calculator updates
        $(document).on('updated_shipping_method', function() {
            setTimeout(processAllPrices, 100);
        });
        
        // Handle any AJAX updates that might affect shipping
        $(document).ajaxComplete(function(event, xhr, settings) {
            // Check if this is a WooCommerce AJAX request or shipping-related
            if (settings.url && (
                settings.url.indexOf('wc-ajax=') > -1 || 
                settings.url.indexOf('update_order_review') > -1 ||
                settings.url.indexOf('get_refreshed_fragments') > -1 ||
                settings.url.indexOf('shipping') > -1 ||
                settings.url.indexOf('speedy') > -1 ||
                (settings.data && typeof settings.data === 'string' && settings.data.indexOf('shipping') > -1)
            )) {
                setTimeout(function() {
                    processAllPrices();
                    // Extra check for shipping methods specifically
                    setTimeout(function() {
                        if ($('#shipping_method .woocommerce-Price-amount').length > 0 && 
                            $('#shipping_method .eur-price').length === 0) {
                            processShippingMethods();
                        }
                    }, 200);
                }, 100);
            }
        });
        
        // Additional event listener specifically for when shipping method HTML gets updated
        $(document).on('DOMNodeInserted', '#shipping_method', function() {
            setTimeout(function() {
                processShippingMethods();
            }, 50);
        });
        
        // Periodic check for stubborn dynamic updates (every 2 seconds on cart/checkout)
        if (document.querySelector('.woocommerce-cart, .woocommerce-checkout')) {
            setInterval(function() {
                // Only run if there are shipping methods without EUR that should have EUR
                var needsUpdate = false;
                $('#shipping_method li, .woocommerce-shipping-methods li').each(function() {
                    var $li = $(this);
                    var $priceSpan = $li.find('.woocommerce-Price-amount');
                    var $eurSpan = $li.find('.eur-price');
                    var labelText = $li.find('label').text();
                    
                    // Check if this should have EUR but doesn't
                    if ($priceSpan.length > 0 && $eurSpan.length === 0 && 
                        (!labelText || (labelText.indexOf('€') === -1 && labelText.indexOf('EUR') === -1))) {
                        var text = $priceSpan.text().trim();
                        if (text && text.match(/\d+[,]\d{2}\s*(?:лв\.|BGN)?/)) {
                            needsUpdate = true;
                            return false; // Break out of each loop
                        }
                    }
                });
                
                if (needsUpdate) {
                    processAllPrices();
                }
            }, 2000);
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(init);
    
    // Also initialize when page is fully loaded (for cached pages)
    $(window).on('load', processAllPrices);

})(jQuery); 