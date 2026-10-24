(() => {
    'use strict';

    const forms = document.querySelectorAll('form[data-disable-on-submit]');
    forms.forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Processing…';
            });
        });
    });

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message = element.getAttribute('data-confirm') || 'Continue?';
            if (!window.confirm(message)) event.preventDefault();
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.querySelector(button.getAttribute('data-password-toggle'));
            if (!target) return;
            const reveal = target.type === 'password';
            target.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            const icon = button.querySelector('i');
            if (icon) icon.className = reveal ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    document.querySelectorAll('[data-image-preview-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const preview = document.querySelector('[data-image-preview]');
            if (!file || !preview) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return;
            const reader = new FileReader();
            reader.addEventListener('load', () => { if (typeof reader.result === 'string') preview.src = reader.result; });
            reader.readAsDataURL(file);
        });
    });

    window.Franscoffee = {
        peso(value) {
            const amount = Number(value || 0);
            return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
        },
        escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value ?? '');
            return div.innerHTML;
        }
    };

    function updateProductPreviewTotal() {
        const baseNode = document.querySelector('.product-price[data-base-price]');
        const totalNode = document.querySelector('[data-product-preview-total]');
        if (!baseNode || !totalNode) return;

        let total = Number(baseNode.dataset.basePrice || 0);
        const selectedSize = document.querySelector('input[name="size_id"]:checked');
        if (selectedSize) total += Number(selectedSize.dataset.priceAdjustment || 0);
        const selectedMilk = document.querySelector('input[name="milk_addon_id"]:checked');
        if (selectedMilk) total += Number(selectedMilk.dataset.milkPrice || 0);
        document.querySelectorAll('[data-addon-price]:checked').forEach((input) => {
            total += Number(input.dataset.addonPrice || 0);
        });
        totalNode.textContent = window.Franscoffee.peso(total);
    }

    document.querySelectorAll('input[name="size_id"], input[name="milk_addon_id"], [data-addon-price]').forEach((input) => {
        input.addEventListener('change', updateProductPreviewTotal);
    });
    updateProductPreviewTotal();

    document.querySelectorAll('[data-quantity-picker]').forEach((picker) => {
        const input = picker.querySelector('input[type="number"]');
        if (!input) return;
        const min = Number(input.min || 1);
        const max = Number(input.max || 20);
        const clamp = (value) => Math.min(max, Math.max(min, Number(value || min)));
        picker.querySelector('[data-qty-minus]')?.addEventListener('click', () => { input.value = String(clamp(Number(input.value) - 1)); });
        picker.querySelector('[data-qty-plus]')?.addEventListener('click', () => { input.value = String(clamp(Number(input.value) + 1)); });
        input.addEventListener('change', () => { input.value = String(clamp(input.value)); });
    });

    function syncCheckout() {
        const form = document.querySelector('[data-checkout-form]');
        if (!form) return;
        const fulfillment = form.querySelector('input[name="fulfillment_type"]:checked')?.value || 'PICKUP';
        const deliverySection = form.querySelector('[data-delivery-section]');
        if (deliverySection) deliverySection.hidden = fulfillment !== 'DELIVERY';

        form.querySelectorAll('[data-payment-code]').forEach((label) => {
            const input = label.querySelector('input[name="payment_method"]');
            if (!input) return;
            const code = input.value;
            const allowed = code === 'GCASH' || code === 'MAYA' || (fulfillment === 'PICKUP' && code === 'COP') || (fulfillment === 'DELIVERY' && code === 'COD');
            label.hidden = !allowed;
            input.disabled = !allowed;
            if (!allowed && input.checked) input.checked = false;
        });
        if (!form.querySelector('input[name="payment_method"]:checked')) {
            const first = [...form.querySelectorAll('input[name="payment_method"]')].find((input) => !input.disabled);
            if (first) first.checked = true;
        }

        const savedId = Number(form.querySelector('input[name="saved_address_id"]:checked')?.value || 0);
        const newFields = form.querySelector('[data-new-address-fields]');
        if (newFields) newFields.hidden = fulfillment !== 'DELIVERY' || savedId > 0;

        const payment = form.querySelector('input[name="payment_method"]:checked')?.value || '';
        form.querySelectorAll('[data-ewallet-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.ewalletPanel === payment));
        const online = payment === 'GCASH' || payment === 'MAYA';
        form.querySelector('[data-online-payment-fields]')?.classList.toggle('active', online);

        const subtotalNode = form.querySelector('[data-checkout-subtotal]');
        const discountNode = form.querySelector('[data-checkout-discount]');
        const feeNode = form.querySelector('[data-checkout-delivery]');
        const totalNode = form.querySelector('[data-checkout-total]');
        if (subtotalNode && feeNode && totalNode) {
            const subtotal = Number(subtotalNode.dataset.checkoutSubtotal || 0);
            const discount = Number(discountNode?.dataset.discount || 0);
            const fee = fulfillment === 'DELIVERY' ? Number(feeNode.dataset.deliveryFee || 0) : 0;
            feeNode.textContent = window.Franscoffee.peso(fee);
            if (discountNode) discountNode.textContent = '−' + window.Franscoffee.peso(discount);
            totalNode.textContent = window.Franscoffee.peso(Math.max(0, subtotal - discount) + fee);
        }
    }

    document.querySelectorAll('[data-checkout-form] input[name="fulfillment_type"], [data-checkout-form] input[name="payment_method"], [data-checkout-form] input[name="saved_address_id"]').forEach((input) => input.addEventListener('change', syncCheckout));
    syncCheckout();

    const promoInput = document.querySelector('[data-promo-code]');
    promoInput?.addEventListener('input', () => {
        const form = document.querySelector('[data-checkout-form]');
        const discountNode = form?.querySelector('[data-checkout-discount]');
        const message = form?.querySelector('[data-promo-message]');
        if (discountNode) discountNode.dataset.discount = '0';
        if (message) { message.textContent = 'Click Apply to validate this code. It will be checked again when you place the order.'; message.classList.remove('text-danger','text-success'); }
        syncCheckout();
    });

    const promoButton = document.querySelector('[data-promo-apply]');
    promoButton?.addEventListener('click', async () => {
        const form = document.querySelector('[data-checkout-form]');
        const summary = document.querySelector('[data-promo-endpoint]');
        const input = form?.querySelector('[data-promo-code]');
        const message = form?.querySelector('[data-promo-message]');
        const discountNode = form?.querySelector('[data-checkout-discount]');
        if (!form || !summary || !input || !discountNode) return;
        const code = String(input.value || '').trim().toUpperCase();
        input.value = code;
        promoButton.disabled = true;
        try {
            const response = await fetch(`${summary.dataset.promoEndpoint}?code=${encodeURIComponent(code)}`, {headers:{'Accept':'application/json'}});
            const data = await response.json();
            const discount = data.ok ? Number(data.discount || 0) : 0;
            discountNode.dataset.discount = String(discount);
            if (message) { message.textContent = data.message || (data.ok ? 'Promo applied.' : 'Promo is not available.'); message.classList.toggle('text-danger', !data.ok); message.classList.toggle('text-success', !!data.ok); }
            syncCheckout();
        } catch (_) {
            discountNode.dataset.discount = '0';
            if (message) { message.textContent = 'Could not validate the promo right now. It will still be checked when you place the order.'; message.classList.add('text-danger'); }
            syncCheckout();
        } finally { promoButton.disabled = false; }
    });

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.dataset.copyText || '';
            try {
                await navigator.clipboard.writeText(text);
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i> Copied';
                setTimeout(() => { button.innerHTML = original; }, 1400);
            } catch (_) { window.prompt('Copy payment number:', text); }
        });
    });


    function initPOS() {
        const form = document.querySelector('[data-pos-form]');
        const catalogNode = document.getElementById('posCatalogData');
        const customersNode = document.getElementById('posCustomerData');
        if (!form || !catalogNode || !customersNode) return;

        let catalog = [];
        let customers = [];
        try { catalog = JSON.parse(catalogNode.textContent || '[]'); } catch (_) {}
        try { customers = JSON.parse(customersNode.textContent || '[]'); } catch (_) {}
        const productMap = new Map(catalog.map((p) => [Number(p.id), p]));
        let cart = [];
        let activeProduct = null;
        let selectedCustomer = null;

        const itemsJson = form.querySelector('[data-pos-items-json]');
        const customerIdInput = form.querySelector('[data-pos-customer-id]');
        const cartLines = form.querySelector('[data-pos-cart-lines]');
        const subtotalNode = form.querySelector('[data-pos-subtotal]');
        const discountNode = form.querySelector('[data-pos-discount]');
        const totalNode = form.querySelector('[data-pos-total]');
        const discountType = form.querySelector('[data-pos-discount-type]');
        const discountValue = form.querySelector('[data-pos-discount-value]');
        const cashReceived = form.querySelector('[data-pos-cash-received]');
        const changeNode = form.querySelector('[data-pos-change]');
        const submit = form.querySelector('[data-pos-submit]');
        const referenceField = form.querySelector('#reference_number');
        const cashFields = form.querySelector('[data-pos-cash-fields]');
        const referenceFields = form.querySelector('[data-pos-reference-fields]');

        const esc = window.Franscoffee.escapeHtml;
        const peso = window.Franscoffee.peso;
        const n = (value) => Number(value || 0);

        function productOptionsLabel(item) {
            const labels = [];
            if (item.size_name) labels.push(item.size_name);
            if (item.temperature) labels.push(item.temperature === 'ICED' ? 'Iced' : 'Hot');
            if (item.sugar_level !== undefined && item.sugar_level !== null && item.has_sizes) labels.push(`${item.sugar_level}% sugar`);
            if (item.milk_name) labels.push(item.milk_name);
            (item.addon_names || []).forEach((name) => labels.push(name));
            return labels.join(' · ');
        }

        function signature(item) {
            return JSON.stringify({p:item.product_id,s:item.size_id||0,t:item.temperature||'',g:item.sugar_level||'',m:item.milk_addon_id||0,a:[...(item.addon_ids||[])].map(Number).sort((a,b)=>a-b)});
        }

        function calcTotals() {
            const subtotal = cart.reduce((sum, item) => sum + n(item.preview_unit_price) * n(item.quantity), 0);
            const type = discountType?.value || 'NONE';
            let value = Math.max(0, n(discountValue?.value));
            let discount = 0;
            if (type === 'PERCENT') discount = subtotal * Math.min(100, value) / 100;
            if (type === 'FIXED') discount = Math.min(subtotal, value);
            discount = Math.min(subtotal, Math.round(discount * 100) / 100);
            const total = Math.max(0, Math.round((subtotal - discount) * 100) / 100);
            if (subtotalNode) subtotalNode.textContent = peso(subtotal);
            if (discountNode) discountNode.textContent = '−' + peso(discount);
            if (totalNode) totalNode.textContent = peso(total);
            const payment = form.querySelector('input[name="payment_method"]:checked')?.value || 'CASH';
            const cash = Math.max(0, n(cashReceived?.value));
            const change = payment === 'CASH' ? Math.max(0, cash - total) : 0;
            if (changeNode) changeNode.textContent = peso(change);
            const referenceOk = payment === 'CASH' || String(referenceField?.value || '').trim().length > 0;
            const paymentOk = payment !== 'CASH' || cash >= total;
            if (submit) submit.disabled = cart.length === 0 || !paymentOk || !referenceOk;
            return {subtotal,discount,total};
        }

        function syncHiddenItems() {
            if (!itemsJson) return;
            itemsJson.value = JSON.stringify(cart.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
                size_id: item.size_id || 0,
                temperature: item.temperature || '',
                sugar_level: item.sugar_level ?? '100',
                milk_addon_id: item.milk_addon_id || 0,
                addon_ids: item.addon_ids || []
            })));
        }

        function renderCart() {
            if (!cartLines) return;
            if (!cart.length) {
                cartLines.innerHTML = '<div class="pos-empty-cart"><i class="bi bi-bag"></i><strong>No items yet</strong><span>Select a product to start the order.</span></div>';
            } else {
                cartLines.innerHTML = cart.map((item, index) => `
                    <div class="pos-cart-line">
                        <div class="pos-cart-line-top"><div><strong>${esc(item.product_name)}</strong><span class="pos-cart-line-options">${esc(productOptionsLabel(item))}</span></div><span class="pos-cart-line-price">${peso(n(item.preview_unit_price)*n(item.quantity))}</span></div>
                        <div class="pos-cart-line-actions"><div class="pos-mini-qty"><button type="button" data-pos-line-minus="${index}" aria-label="Decrease">−</button><span>${item.quantity}</span><button type="button" data-pos-line-plus="${index}" aria-label="Increase">+</button></div><button type="button" class="pos-remove-line" data-pos-line-remove="${index}"><i class="bi bi-trash me-1"></i>Remove</button></div>
                    </div>`).join('');
            }
            syncHiddenItems();
            calcTotals();
        }

        cartLines?.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const minus = button.dataset.posLineMinus;
            const plus = button.dataset.posLinePlus;
            const remove = button.dataset.posLineRemove;
            if (minus !== undefined) { const i=Number(minus); if (cart[i]) { cart[i].quantity=Math.max(1,cart[i].quantity-1); renderCart(); } }
            if (plus !== undefined) { const i=Number(plus); if (cart[i]) { cart[i].quantity=Math.min(99,cart[i].quantity+1); renderCart(); } }
            if (remove !== undefined) { cart.splice(Number(remove),1); renderCart(); }
        });
        form.querySelector('[data-pos-clear]')?.addEventListener('click', () => { if (!cart.length || window.confirm('Clear the current POS order?')) { cart=[]; renderCart(); } });

        discountType?.addEventListener('change', () => { if (discountValue) { discountValue.disabled=discountType.value==='NONE'; if (discountType.value==='NONE') discountValue.value='0'; } calcTotals(); });
        discountValue?.addEventListener('input', calcTotals);
        cashReceived?.addEventListener('input', calcTotals);
        referenceField?.addEventListener('input', calcTotals);
        function syncPosPayment() {
            const radio = form.querySelector('input[name="payment_method"]:checked');
            const cash = !radio || radio.value === 'CASH';
            if (cashFields) cashFields.hidden = !cash;
            if (referenceFields) referenceFields.hidden = cash;
            const receiver = form.querySelector('[data-pos-ewallet-receiver]');
            if (receiver) {
                const receiverName = String(radio?.dataset.receiverName || '');
                const receiverNumber = String(radio?.dataset.receiverNumber || '');
                receiver.hidden = cash || (!receiverName && !receiverNumber);
                const nameNode = receiver.querySelector('[data-pos-receiver-name]');
                const numberNode = receiver.querySelector('[data-pos-receiver-number]');
                if (nameNode) nameNode.textContent = receiverName;
                if (numberNode) numberNode.textContent = receiverNumber;
            }
            calcTotals();
        }
        form.querySelectorAll('[data-pos-payment]').forEach((radio) => radio.addEventListener('change', syncPosPayment));

        const search = form.querySelector('[data-pos-product-search]');
        const tabs = form.querySelector('[data-pos-category-tabs]');
        let activeCategory = 'all';
        function filterProducts() {
            const q = String(search?.value || '').trim().toLowerCase();
            let visible = 0;
            form.querySelectorAll('[data-pos-product-card]').forEach((card) => {
                const categoryOk = activeCategory === 'all' || card.dataset.category === activeCategory;
                const searchOk = !q || String(card.dataset.search || '').includes(q);
                const show = categoryOk && searchOk;
                card.hidden = !show;
                if (show) visible++;
            });
            const empty = form.querySelector('[data-pos-empty-products]');
            if (empty) empty.hidden = visible > 0;
        }
        search?.addEventListener('input', filterProducts);
        tabs?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-pos-category]');
            if (!button) return;
            activeCategory = button.dataset.posCategory || 'all';
            tabs.querySelectorAll('button').forEach((b)=>b.classList.toggle('active',b===button));
            filterProducts();
        });

        const customerSearch = form.querySelector('[data-pos-customer-search]');
        const customerResults = form.querySelector('[data-pos-customer-results]');
        const customerSelected = form.querySelector('[data-pos-selected-customer]');
        const customerClear = form.querySelector('[data-pos-customer-clear]');
        function renderCustomerSelected() {
            if (!customerSelected || !customerIdInput) return;
            if (!selectedCustomer) {
                customerIdInput.value='0'; customerSelected.innerHTML='<i class="bi bi-person-walking"></i><div><strong>Walk-in Customer</strong><span>No account attached</span></div>';
                if (customerClear) customerClear.hidden=true;
                return;
            }
            customerIdInput.value=String(selectedCustomer.id);
            customerSelected.innerHTML=`<i class="bi bi-person-check"></i><div><strong>${esc(selectedCustomer.first_name+' '+selectedCustomer.last_name)}</strong><span>${esc(selectedCustomer.email || selectedCustomer.phone || 'Customer account')}</span></div>`;
            if (customerClear) customerClear.hidden=false;
        }
        function findCustomers() {
            if (!customerResults) return;
            const q=String(customerSearch?.value||'').trim().toLowerCase();
            if (!q) { customerResults.hidden=true; customerResults.innerHTML=''; return; }
            const matches=customers.filter((c)=>`${c.first_name} ${c.last_name} ${c.email||''} ${c.phone||''}`.toLowerCase().includes(q)).slice(0,8);
            customerResults.innerHTML=matches.length?matches.map((c)=>`<button type="button" class="pos-customer-result" data-pos-customer-pick="${c.id}"><strong>${esc(c.first_name+' '+c.last_name)}</strong><small>${esc(c.email||'')}${c.phone?' · '+esc(c.phone):''}</small></button>`).join(''):'<div class="p-2 small text-muted">No customer found. Keep as walk-in.</div>';
            customerResults.hidden=false;
        }
        customerSearch?.addEventListener('input', findCustomers);
        customerResults?.addEventListener('click',(event)=>{ const b=event.target.closest('[data-pos-customer-pick]'); if(!b)return; selectedCustomer=customers.find((c)=>Number(c.id)===Number(b.dataset.posCustomerPick))||null; if(customerSearch)customerSearch.value=selectedCustomer?`${selectedCustomer.first_name} ${selectedCustomer.last_name}`:''; customerResults.hidden=true; renderCustomerSelected(); });
        customerClear?.addEventListener('click',()=>{selectedCustomer=null;if(customerSearch)customerSearch.value='';if(customerResults)customerResults.hidden=true;renderCustomerSelected();});

        const modalEl = document.getElementById('posProductModal');
        const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
        const title = modalEl?.querySelector('[data-pos-modal-title]');
        const category = modalEl?.querySelector('[data-pos-modal-category]');
        const image = modalEl?.querySelector('[data-pos-modal-image]');
        const base = modalEl?.querySelector('[data-pos-modal-base]');
        const sizesWrap = modalEl?.querySelector('[data-pos-sizes]');
        const sizeSection = modalEl?.querySelector('[data-pos-size-section]');
        const tempSection = modalEl?.querySelector('[data-pos-temperature-section]');
        const sugarSection = modalEl?.querySelector('[data-pos-sugar-section]');
        const milkSection = modalEl?.querySelector('[data-pos-milk-section]');
        const milksWrap = modalEl?.querySelector('[data-pos-milks]');
        const addonsSection = modalEl?.querySelector('[data-pos-addons-section]');
        const addonsWrap = modalEl?.querySelector('[data-pos-addons]');
        const sugar = modalEl?.querySelector('[data-pos-sugar]');
        const qtyInput = modalEl?.querySelector('[data-pos-modal-qty]');
        const modalTotal = modalEl?.querySelector('[data-pos-modal-total]');

        function modalConfig() {
            if (!activeProduct) return null;
            const sizes = activeProduct.sizes || [];
            const sizeInput = modalEl?.querySelector('input[name="pos_modal_size"]:checked');
            const tempInput = modalEl?.querySelector('input[name="pos_modal_temperature"]:checked');
            const milkInput = modalEl?.querySelector('input[name="pos_modal_milk"]:checked');
            const addonInputs = [...(modalEl?.querySelectorAll('input[name="pos_modal_addon"]:checked') || [])];
            const size = sizes.find((s)=>Number(s.id)===Number(sizeInput?.value)) || null;
            const milk = (activeProduct.milk_addons||[]).find((a)=>Number(a.id)===Number(milkInput?.value)) || null;
            const addonIds=addonInputs.map((i)=>Number(i.value));
            const addons=(activeProduct.addons||[]).filter((a)=>addonIds.includes(Number(a.id)));
            const hasSizes=sizes.length>0;
            const quantity=Math.max(1,Math.min(20,Number(qtyInput?.value||1)));
            const unit=n(activeProduct.base_price)+n(size?.price_adjustment)+n(milk?.price)+addons.reduce((sum,a)=>sum+n(a.price),0);
            return {product_id:Number(activeProduct.id),product_name:activeProduct.name,quantity,size_id:size?Number(size.id):0,size_name:size?.name||'',temperature:hasSizes?(tempInput?.value||activeProduct.default_temperature||'HOT'):'',sugar_level:hasSizes?(sugar?.value||'100'):'',milk_addon_id:milk?Number(milk.id):0,milk_name:milk?.name||'',addon_ids:addonIds,addon_names:addons.map((a)=>a.name),preview_unit_price:unit,has_sizes:hasSizes};
        }
        function updateModalTotal(){const c=modalConfig();if(modalTotal&&c)modalTotal.textContent=peso(n(c.preview_unit_price)*n(c.quantity));}
        function openProduct(productId) {
            activeProduct=productMap.get(Number(productId)); if(!activeProduct||!modalEl)return;
            if(title)title.textContent=activeProduct.name;if(category)category.textContent=activeProduct.category_name||'MENU ITEM';if(image){image.src=activeProduct.image_url||'';image.alt=activeProduct.name;}if(base)base.textContent=peso(activeProduct.base_price);
            const sizes=activeProduct.sizes||[]; if(sizeSection)sizeSection.hidden=!sizes.length; if(tempSection)tempSection.hidden=!sizes.length; if(sugarSection)sugarSection.hidden=!sizes.length;
            if(sizesWrap)sizesWrap.innerHTML=sizes.map((size,i)=>`<label><input type="radio" name="pos_modal_size" value="${size.id}" ${i===0?'checked':''}><span>${esc(size.name)}${n(size.price_adjustment)>0?'<small class="d-block text-muted">+'+peso(size.price_adjustment)+'</small>':''}</span></label>`).join('');
            modalEl.querySelectorAll('input[name="pos_modal_temperature"]').forEach((input)=>input.checked=input.value===(activeProduct.default_temperature||'HOT'));
            if(sugar)sugar.value='100';
            const milks=activeProduct.milk_addons||[]; if(milkSection)milkSection.hidden=!sizes.length || !milks.length; if(milksWrap)milksWrap.innerHTML=`<label><input type="radio" name="pos_modal_milk" value="0" checked><span>Regular Milk</span></label>`+milks.map((milk)=>`<label><input type="radio" name="pos_modal_milk" value="${milk.id}"><span>${esc(milk.name)}<small class="d-block text-muted">+${peso(milk.price)}</small></span></label>`).join('');
            const addons=activeProduct.addons||[]; if(addonsSection)addonsSection.hidden=!addons.length; if(addonsWrap)addonsWrap.innerHTML=addons.map((addon)=>`<label><span><input type="checkbox" name="pos_modal_addon" value="${addon.id}">${esc(addon.name)}</span><strong>+${peso(addon.price)}</strong></label>`).join('');
            if(qtyInput)qtyInput.value='1';updateModalTotal();modal?.show();
        }
        form.querySelector('[data-pos-product-grid]')?.addEventListener('click',(event)=>{const b=event.target.closest('[data-pos-open-product]');if(b)openProduct(b.dataset.posOpenProduct);});
        modalEl?.addEventListener('change',updateModalTotal);
        qtyInput?.addEventListener('input',()=>{qtyInput.value=String(Math.max(1,Math.min(20,Number(qtyInput.value||1))));updateModalTotal();});
        modalEl?.querySelector('[data-pos-modal-minus]')?.addEventListener('click',()=>{if(qtyInput){qtyInput.value=String(Math.max(1,Number(qtyInput.value)-1));updateModalTotal();}});
        modalEl?.querySelector('[data-pos-modal-plus]')?.addEventListener('click',()=>{if(qtyInput){qtyInput.value=String(Math.min(20,Number(qtyInput.value)+1));updateModalTotal();}});
        modalEl?.querySelector('[data-pos-add-item]')?.addEventListener('click',()=>{const item=modalConfig();if(!item)return;const sig=signature(item);const existing=cart.find((x)=>signature(x)===sig);if(existing)existing.quantity=Math.min(99,existing.quantity+item.quantity);else cart.push(item);renderCart();modal?.hide();});

        form.addEventListener('submit',(event)=>{const totals=calcTotals();if(!cart.length){event.preventDefault();return;}const payment=form.querySelector('input[name="payment_method"]:checked')?.value||'CASH';if(payment==='CASH'&&n(cashReceived?.value)<totals.total){event.preventDefault();window.alert('Enter enough cash received before completing the sale.');return;}if(payment!=='CASH'&&!String(referenceField?.value||'').trim()){event.preventDefault();window.alert('Enter the e-wallet reference number before completing the sale.');return;}syncHiddenItems();});
        syncPosPayment();renderCustomerSelected();renderCart();
    }

    initPOS();

    function initInventoryTransactions() {
        const form = document.getElementById('inventoryTransactionForm');
        if (!form) return;
        const type = form.querySelector('#txType');
        const quantity = form.querySelector('[data-tx-quantity]');
        const physical = form.querySelector('[data-tx-physical]');
        const cost = form.querySelector('[data-tx-cost]');
        const quantityInput = form.querySelector('#txQty');
        const physicalInput = form.querySelector('#physicalCount');
        function sync() {
            const adjustment = type?.value === 'ADJUSTMENT';
            quantity?.classList.toggle('d-none', adjustment);
            physical?.classList.toggle('d-none', !adjustment);
            cost?.classList.toggle('d-none', adjustment);
            if (quantityInput) quantityInput.required = !adjustment;
            if (physicalInput) physicalInput.required = adjustment;
        }
        type?.addEventListener('change', sync);
        sync();
    }

    initInventoryTransactions();


    function initAdminUserForm() {
        const form = document.getElementById('adminUserForm');
        if (!form) return;
        const role = form.querySelector('[data-admin-user-role]');
        const staffFields = form.querySelector('[data-admin-staff-fields]');
        function sync() {
            const show = role?.value === 'STAFF';
            if (staffFields) staffFields.hidden = !show;
        }
        role?.addEventListener('change', sync);
        sync();
    }

    initAdminUserForm();

})();
