/**
 * AboveWP Bulgarian Eurozone - JavaScript for WooCommerce Blocks
 * 
 * Handles bidirectional dual currency display (BGN ⇄ EUR) for WooCommerce Gutenberg blocks
 * with configurable secondary currency positioning (left or right of primary price)
 */
(function($) {
    'use strict';

    // Ensure we have the conversion data
    if (typeof abovewpBGE === 'undefined') {
        console.error('AboveWP Bulgarian Eurozone: Missing conversion data');
        return;
    }
    
    // Get conversion rate, currency info, position, and format from localized data
    const conversionRate = abovewpBGE.conversionRate;
    const primaryCurrency = abovewpBGE.primaryCurrency; // 'BGN' or 'EUR'
    const secondaryCurrency = abovewpBGE.secondaryCurrency; // 'EUR' or 'BGN'
    const eurLabel = abovewpBGE.eurLabel || '€';
    const bgnLabel = abovewpBGE.bgnLabel || 'лв.';
    const secondaryLabel = abovewpBGE.secondaryLabel;
    const eurPosition = abovewpBGE.eurPosition || 'right';
    const eurFormat = abovewpBGE.eurFormat || 'brackets';
    const bgnRounding = abovewpBGE.bgnRounding || 'smart';

    /**
     * Normalize price string to float
     * 
     * @param {string} price - Price string (may include thousands separators)
     * @return {number} - Normalized price as float
     */
    function normalizePrice(price) {
        let normalizedPrice = String(price);
        
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
        
        return parseFloat(normalizedPrice);
    }

    /**
     * Convert BGN to EUR
     * 
     * @param {string} bgnPrice - Price in BGN (may include thousands separators)
     * @return {string} - Formatted price in EUR with 2 decimal places
     */
    function convertBgnToEur(bgnPrice) {
        return (normalizePrice(bgnPrice) / conversionRate).toFixed(2);
    }

    /**
     * Convert EUR to BGN
     *
     * @param {string} eurPrice - Price in EUR (may include thousands separators)
     * @return {string} - Formatted price in BGN with 2 decimal places
     */
    function convertEurToBgn(eurPrice) {
        var raw = normalizePrice(eurPrice) * conversionRate;
        var rounded = Math.round(raw * 100) / 100;

        if (bgnRounding === 'smart') {
            var nearestInt = Math.round(rounded);
            if (Math.abs(rounded - nearestInt) < 0.015) {
                return nearestInt.toFixed(2);
            }
        }
        return rounded.toFixed(2);
    }

    /**
     * Convert price from primary to secondary currency
     * 
     * @param {string} price - Price in primary currency
     * @return {string} - Formatted price in secondary currency with 2 decimal places
     */
    function convertToSecondary(price) {
        if (primaryCurrency === 'BGN') {
            return convertBgnToEur(price);
        } else if (primaryCurrency === 'EUR') {
            return convertEurToBgn(price);
        }
        return price;
    }

    /**
     * Format secondary currency price with label
     * 
     * @param {number|string} secondaryPrice - Price in secondary currency
     * @return {string} - Formatted price with secondary currency label
     */
    function formatSecondaryPrice(secondaryPrice) {
        if (eurFormat === 'divider') {
            return '/ ' + secondaryPrice + ' ' + secondaryLabel;
        } else {
            return '(' + secondaryPrice + ' ' + secondaryLabel + ')';
        }
    }

    /**
     * Format dual currency price based on position setting
     * 
     * @param {string} primaryPriceHtml - The original primary currency price HTML/text
     * @param {number|string} secondaryPrice - The secondary currency price amount
     * @return {string} - The formatted dual currency price
     */
    function formatDualPrice(primaryPriceHtml, secondaryPrice) {
        const secondaryFormatted = formatSecondaryPrice(secondaryPrice);
        const secondarySpan = '<span class="eur-price">' + secondaryFormatted + '</span>';
        
        if (eurPosition === 'left') {
            return secondarySpan + ' ' + primaryPriceHtml;
        } else {
            return primaryPriceHtml + ' ' + secondarySpan;
        }
    }
    
    /**
     * Check if element already has a secondary currency price
     * 
     * @param {Element} element - The element to check
     * @return {boolean} - True if element already has secondary currency price
     */
    function hasSecondaryPrice(element) {
        const $element = $(element);
        
        // Check for span with eur-price class within or next to the element
        if ($element.find('.eur-price').length > 0 || 
            $element.siblings('.eur-price').length > 0 ||
            $element.next('.eur-price').length > 0 ||
            $element.prev('.eur-price').length > 0) {
            return true;
        }
        
        // Check parent containers for secondary currency spans
        if ($element.parent().find('.eur-price').length > 0) {
            return true;
        }
        
        // For shipping methods, check the entire list item
        if ($element.closest('li').find('.eur-price').length > 0) {
            return true;
        }
        
        // Check if the text already contains secondary currency symbol
        const text = $element.text();
        if (text.includes('(' + secondaryLabel + ')') || text.includes(secondaryLabel + ')') || 
            text.includes('/ ' + secondaryLabel) || text.includes('/ ' + secondaryLabel + ')')) {
            return true;
        }
        
        // For mini-cart specifically
        if ($element.closest('.mini_cart_item').length > 0 && 
            $element.closest('.mini_cart_item').find('.eur-price').length > 0) {
            return true;
        }
        
        return false;
    }

    // Keep old function name for backward compatibility
    const hasEurPrice = hasSecondaryPrice;

    /**
     * Get price pattern based on primary currency
     * 
     * @return {RegExp} - Price pattern for the primary currency
     */
    function getPricePattern() {
        if (primaryCurrency === 'BGN') {
            // Match BGN price pattern with thousands separators
            // Examples: "1 650,00 лв.", "1.650,00 лв.", "25,00 лв.", "1650,00"
            return /(\d+(?:[\s.]\d{3})*[.,]\d{2})\s*(?:лв\.|BGN)?/;
        } else if (primaryCurrency === 'EUR') {
            // Match EUR price pattern
            // Examples: "1 650,00 €", "1.650,00 €", "25,00 €", "€1650,00"
            return /(?:€\s*)?(\d+(?:[\s.]\d{3})*[.,]\d{2})\s*(?:€|EUR)?/;
        }
        return /(\d+(?:[\s.]\d{3})*[.,]\d{2})/;
    }

    /**
     * Add secondary currency price to a price element based on position setting
     * 
     * @param {Element} element - The element containing the price
     */
    function addSecondaryPrice(element) {
        // Skip if already processed
        if (hasSecondaryPrice(element)) {
            return;
        }
        
        const $element = $(element);
        const text = $element.text().trim();
        
        const pricePattern = getPricePattern();
        const match = text.match(pricePattern);
        
        if (match) {
            const pricePrimary = match[1];
            const priceSecondary = convertToSecondary(pricePrimary);
            
            // Create the secondary currency price element
            const $secondarySpan = $('<span class="eur-price">' + formatSecondaryPrice(priceSecondary) + '</span>');
            
            // Add based on position setting
            if (eurPosition === 'left') {
                $element.prepend($secondarySpan).prepend(' ');
            } else {
                $element.append(' ').append($secondarySpan);
            }
        }
    }

    // Keep old function name for backward compatibility
    const addEurPrice = addSecondaryPrice;

    /**
     * Replace element content with dual currency price based on position setting
     * 
     * @param {Element} element - The element containing the price
     */
    function replaceDualPrice(element) {
        // Skip if already processed
        if (hasSecondaryPrice(element)) {
            return;
        }
        
        const $element = $(element);
        let pricePrimary;
        
        // Check if this is a sale price scenario (has both regular and sale price)
        const $salePrice = $element.find('ins.wc-block-components-product-price__value, .wc-block-components-product-price__value.is-discounted');
        const $regularPrice = $element.find('del.wc-block-components-product-price__regular');
        
        if ($salePrice.length > 0 && $regularPrice.length > 0) {
            // This is a sale - use the sale price (ins element)
            const salePriceText = $salePrice.text().trim();
            const pricePattern = getPricePattern();
            const match = salePriceText.match(pricePattern);
            
            if (match) {
                pricePrimary = match[1];
                const priceSecondary = convertToSecondary(pricePrimary);
                
                // Add secondary currency after the sale price (ins element)
                const secondaryFormatted = formatSecondaryPrice(priceSecondary);
                const secondarySpan = '<span class="eur-price">' + secondaryFormatted + '</span>';
                
                if (eurPosition === 'left') {
                    $element.prepend(secondarySpan + ' ');
                } else {
                    $element.append(' ' + secondarySpan);
                }
                return;
            }
        }
        
        // No sale price, process normally
        const originalHtml = $element.html();
        const text = $element.text().trim();
        
        const pricePattern = getPricePattern();
        const match = text.match(pricePattern);
        
        if (match) {
            pricePrimary = match[1];
            const priceSecondary = convertToSecondary(pricePrimary);
            
            // Replace content with dual price
            const dualPriceHtml = formatDualPrice(originalHtml, priceSecondary);
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
            
            // Check if the label already contains secondary currency information (skip these)
            var labelText = $li.find('label').text();
            if (labelText && (labelText.indexOf(secondaryLabel) !== -1)) {
                return; // Skip if label already has secondary currency built in
            }
            
            // Find price spans within this shipping method
            var $priceSpan = $li.find('.woocommerce-Price-amount');
            if ($priceSpan.length > 0) {
                $priceSpan.each(function() {
                    var $this = $(this);
                    var text = $this.text().trim();
                    var html = $this.html(); // Get HTML to handle &nbsp; entities
                    
                    // Skip if no price or price already contains secondary currency text
                    if (!text || text.indexOf(secondaryLabel) !== -1) {
                        return;
                    }
                    
                    // Enhanced price pattern to handle &nbsp; entities and various formats
                    var pricePattern;
                    if (primaryCurrency === 'BGN') {
                        pricePattern = /(\d+(?:[,\s.&nbsp;]\d{3})*[,\.]\d{2})\s*(?:лв\.|BGN)?/;
                    } else {
                        pricePattern = /(?:€\s*)?(\d+(?:[,\s.&nbsp;]\d{3})*[,\.]\d{2})\s*(?:€|EUR)?/;
                    }
                    
                    var priceMatch = text.match(pricePattern) || html.match(pricePattern);
                    
                    if (priceMatch) {
                        var pricePrimaryRaw = priceMatch[1];
                        // Clean up the price: remove &nbsp; entities, spaces, and normalize decimal separator
                        var pricePrimary = pricePrimaryRaw.replace(/&nbsp;/g, '').replace(/\s/g, '').replace(',', '.');
                        var currentPriceSecondary = convertToSecondary(pricePrimary);
                        
                        // Check if there's already a secondary currency price for this shipping method
                        var $existingSecondarySpan = $li.find('.eur-price');
                        if ($existingSecondarySpan.length > 0) {
                            // Extract the existing secondary price
                            var existingSecondaryText = $existingSecondarySpan.text();
                            var existingSecondaryMatch = existingSecondaryText.match(/(\d+[.,]\d{2})/);
                            
                            if (existingSecondaryMatch) {
                                var existingSecondaryPrice = existingSecondaryMatch[1].replace(',', '.');
                                // If the secondary prices don't match (primary price changed), remove old one
                                if (Math.abs(parseFloat(currentPriceSecondary) - parseFloat(existingSecondaryPrice)) > 0.01) {
                                    $existingSecondarySpan.remove();
                                } else {
                                    // Secondary price is correct, skip adding new one
                                    return;
                                }
                            } else {
                                // Can't parse existing secondary price, remove it to be safe
                                $existingSecondarySpan.remove();
                            }
                        }
                        
                        // Add the new/updated secondary currency price
                        var secondaryFormatted = formatSecondaryPrice(currentPriceSecondary);
                        var secondarySpan = '<span class="eur-price">' + secondaryFormatted + '</span>';
                        
                        // When EUR is the site currency, always show EUR first (it's the primary)
                        // When BGN is the site currency, respect the eurPosition setting
                        if (primaryCurrency === 'EUR') {
                            // EUR is primary, so always add BGN (secondary) after
                            $this.after(' ' + secondarySpan);
                        } else if (eurPosition === 'left') {
                            // BGN is primary, EUR can go before or after based on setting
                            $this.before(secondarySpan + ' ');
                        } else {
                            $this.after(' ' + secondarySpan);
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
            
            // Skip if this shipping option already has secondary currency conversion
            if ($option.find('.eur-price').length > 0) {
                return;
            }
            
            // Find the price element within this shipping option
            var $priceElement = $option.find('.wc-block-formatted-money-amount.wc-block-components-formatted-money-amount');
            if ($priceElement.length > 0) {
                $priceElement.each(function() {
                    var $this = $(this);
                    var text = $this.text().trim();
                    
                    // Skip if no price, already has secondary currency, or is free shipping
                    if (!text || text.indexOf(secondaryLabel) !== -1 || text.toLowerCase().indexOf('безплатно') !== -1 || text.toLowerCase().indexOf('free') !== -1) {
                        return;
                    }
                    
                    // Match price pattern based on primary currency
                    var pricePattern;
                    if (primaryCurrency === 'BGN') {
                        pricePattern = /(\d+(?:[,\s.]\d{3})*[,]\d{2})\s*(?:лв\.|BGN)?/;
                    } else {
                        pricePattern = /(?:€\s*)?(\d+(?:[,\s.]\d{3})*[,]\d{2})\s*(?:€|EUR)?/;
                    }
                    
                    var priceMatch = text.match(pricePattern);
                    if (priceMatch) {
                        var pricePrimary = priceMatch[1].replace(/\s/g, '').replace(',', '.');
                        var priceSecondary = convertToSecondary(pricePrimary);
                        var secondaryFormatted = formatSecondaryPrice(priceSecondary);
                        var secondarySpan = '<span class="eur-price">' + secondaryFormatted + '</span>';
                        
                        // When EUR is the site currency, always show EUR first (it's the primary)
                        // When BGN is the site currency, respect the eurPosition setting
                        if (primaryCurrency === 'EUR') {
                            // EUR is primary, so always add BGN (secondary) after
                            $this.after(' ' + secondarySpan);
                        } else if (eurPosition === 'left') {
                            // BGN is primary, EUR can go before or after based on setting
                            $this.before(secondarySpan + ' ');
                        } else {
                            $this.after(' ' + secondarySpan);
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
            
            if (!hasSecondaryPrice(this)) {
                var text = $this.text().trim();
                
                if (text && text.indexOf(secondaryLabel) === -1 && 
                    text.toLowerCase().indexOf('безплатно') === -1 && 
                    text.toLowerCase().indexOf('free') === -1) {
                    
                    var pricePattern = getPricePattern();
                    var priceMatch = text.match(pricePattern);
                    if (priceMatch) {
                        var pricePrimary = priceMatch[1].replace(/\s/g, '').replace(',', '.');
                        var priceSecondary = convertToSecondary(pricePrimary);
                        var secondaryFormatted = formatSecondaryPrice(priceSecondary);
                        var secondarySpan = '<span class="eur-price">' + secondaryFormatted + '</span>';
                        
                        // When EUR is the site currency, always show EUR first (it's the primary)
                        // When BGN is the site currency, respect the eurPosition setting
                        if (primaryCurrency === 'EUR') {
                            // EUR is primary, so always add BGN (secondary) after
                            $this.after(' ' + secondarySpan);
                        } else if (eurPosition === 'left') {
                            // BGN is primary, EUR can go before or after based on setting
                            $this.before(secondarySpan + ' ');
                        } else {
                            $this.after(' ' + secondarySpan);
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
                settings.url.indexOf('econt') > -1 ||
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
                // Only run if there are shipping methods without secondary currency that should have it
                var needsUpdate = false;
                $('#shipping_method li, .woocommerce-shipping-methods li').each(function() {
                    var $li = $(this);
                    var $priceSpan = $li.find('.woocommerce-Price-amount');
                    var $secondarySpan = $li.find('.eur-price');
                    var labelText = $li.find('label').text();
                    
                    // Check if this should have secondary currency but doesn't
                    if ($priceSpan.length > 0 && $secondarySpan.length === 0 && 
                        (!labelText || labelText.indexOf(secondaryLabel) === -1)) {
                        var text = $priceSpan.text().trim();
                        var pricePattern = getPricePattern();
                        if (text && text.match(pricePattern)) {
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