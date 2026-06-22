<?php

require 'db.php';

session_start();
require_once __DIR__ . '/includes/settings.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: main/login.html');
    exit;
}


if (in_array($_SESSION['user_role'], ['admin', 'rider', 'owner'])) {
    if ($_SESSION['user_role'] === 'rider') {
        header('Location: rider_panel.php');
    } else {
        header('Location: admin_dashboard.php');
    }
    exit;
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$wallet_user_id = $_SESSION['bank_unlocked_user_id'] ?? $user_id;


$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "customer"');
$userStmt->execute([$user_id]);
$customer = $userStmt->fetch();

if (!$customer) {
    die('❌ Unauthorized access. Customers only.');
}


try {
    $walletStmt = $pdo->prepare('SELECT balance FROM wallet WHERE user_id = ?');
    $walletStmt->execute([$wallet_user_id]);
    $wallet = $walletStmt->fetch();
    $wallet_balance = $wallet ? $wallet['balance'] : 0.00;
    

    if (!$wallet) {
        $createWallet = $pdo->prepare('INSERT INTO wallet (user_id, balance) VALUES (?, 0.00)');
        $createWallet->execute([$wallet_user_id]);
        $wallet_balance = 0.00;
    }
} catch (Exception $e) {
    $wallet_balance = 0.00;
}

$wallet_owner_name = $customer['name'];
if ($wallet_user_id !== $user_id) {
    $ownerStmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $ownerStmt->execute([$wallet_user_id]);
    $owner = $ownerStmt->fetch();
    if ($owner && !empty($owner['name'])) {
        $wallet_owner_name = $owner['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Your Cart — <?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></title>
    <link rel="stylesheet" href="css/design.css">
</head>
<body>
    <div class="app">
        <header>
            <div class="brand">
                <div class="logo">KM</div>
                <div>
                    <h1 class="site-name"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></h1>
                    <p class="lead">Your Cart</p>
                </div>
            </div>
            <div class="actions">
                <a href="index.php" class="icon-btn" title="Back to Menu">⬅ Menu</a>
                <a href="customer_wallet.php" class="icon-btn" title="My Wallet">💰 Wallet: ₱<?= number_format($wallet_balance, 2) ?><?php if ($wallet_user_id !== $user_id): ?> (<?= htmlspecialchars($wallet_owner_name) ?>)<?php endif; ?></a>
                <a href="customer_profile.php" class="icon-btn" title="My Orders">📦 Orders</a>
            </div>
        </header>

        <main class="cart-main">
            <div class="cart-container">
                <div class="cart-details-col">
                    <h2>Your Cart Items</h2>
                    <div class="cart-list-wrapper">
                        <div class="cart-list" id="cart-list">
                            <div class="empty-message" id="empty-cart">
                                <div class="empty-message-icon">🛒</div>
                                <p>Cart is empty. <a href="index.php">Go back to menu</a></p>
                            </div>
                        </div>
                    </div>

                    <div class="total-summary">
                        <div class="total" id="cart-total">
                            <div>ORDER TOTAL</div>
                            <div>₱0.00</div>
                        </div>
                    </div>

                    <div class="cart-actions-bottom">
                        <button type="button" class="btn" id="clear">Clear Cart</button>
                    </div>
                </div>

                <div class="checkout-form-col">
                    <h3>Delivery Details</h3>
                    
                    <div id="message-container"></div>

                    <form id="checkout-form">
                        <div class="form-group">
                            <label for="customer_name">Full Name:</label>
                            <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="contact_phone">Phone Number:</label>
                            <input type="tel" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="delivery_address">Delivery Address:</label>
                            <textarea id="delivery_address" name="delivery_address" placeholder="Enter your complete delivery address" required><?php echo htmlspecialchars($customer['delivery_address'] ?? ''); ?></textarea>
                        </div>

                        <h3 style="margin-top: 20px; margin-bottom: 15px;">Payment Method</h3>
                        
                        <div class="form-group">
                            <label>
                                <input type="radio" name="payment_method" value="wallet" checked> 
                                💰 Pay with Wallet (Balance: ₱<?= number_format($wallet_balance, 2) ?><?php if ($wallet_user_id !== $user_id): ?> — <?= htmlspecialchars($wallet_owner_name) ?><?php endif; ?>)
                            </label>
                            <?php if ($wallet_user_id !== $user_id): ?>
                                <div class="text-muted" style="font-size: 12px; margin-top: 4px;">Using unlocked wallet for <?= htmlspecialchars($wallet_owner_name) ?>.</div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="radio" name="payment_method" value="digital"> 💳 Digital Wallet (GCash / Bank)
                            </label>
                            <div id="digital-options" style="margin-left: 26px; margin-top: 6px; display:none;">
                                <label style="display:block; margin-bottom:6px;">
                                    <input type="radio" name="payment_channel" value="gcash" checked> GCash (Recommended)
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="payment_channel" value="bank"> Bank Transfer
                                </label>
                                <div id="qr-container" style="margin-top:10px; display:none;">
                                    <div style="font-size:13px; color:#555; margin-bottom:6px;">Scan this QR to pay:</div>
                                    <img id="qr-image" src="images/gcash-qr.png" alt="Payment QR" style="max-width:340px; width:100%; border:1px solid #eee; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.08); cursor:pointer;">
                                    <div style="font-size:12px; color:#777; margin-top:6px;">Tap QR to view larger</div>
                                </div>
                                <div style="margin-top:10px;">
                                    <label class="form-label" style="font-size:13px;">Upload Payment Proof (screenshot)</label>
                                    <input type="file" id="payment_proof" name="payment_proof" accept="image/*" class="form-control" style="max-width:320px;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="radio" name="payment_method" value="cod"> Cash on Delivery (COD)
                            </label>
                        </div>

                        <div id="wallet-warning" class="alert alert-warning mt-2" style="display: none;">
                            <small><i class="bi bi-exclamation-triangle"></i> Insufficient wallet balance. Please <a href="customer_wallet.php">add money to your wallet</a> or choose COD.</small>
                        </div>

                        <button type="submit" class="btn checkout-btn" id="checkout" disabled>
                            Place Order Now
                        </button>
                    </form>
                </div>
            </div>
        </main>

        <footer>
            <span class="site-name"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></span> - <?php echo htmlspecialchars(get_setting('site_description', 'Lasapin ang sarap Pinoy!')); ?>
        </footer>

        
        <div id="qr-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:16px; border-radius:10px; box-shadow:0 12px 30px rgba(0,0,0,0.25); max-width:90vw; max-height:90vh;">
                <div style="text-align:right; margin-bottom:6px;"><button id="qr-modal-close" style="border:none; background:none; font-size:18px; cursor:pointer;">✕</button></div>
                <img id="qr-modal-img" src="images/gcash-qr.png" alt="QR" style="max-width:80vw; max-height:80vh; display:block; margin:0 auto;">
            </div>
        </div>
    </div>

    <script>
        const cartListEl = document.getElementById('cart-list');
        const cartTotalEl = document.getElementById('cart-total');
        const checkoutBtn = document.getElementById('checkout');
        const clearBtn = document.getElementById('clear');
        const emptyCart = document.getElementById('empty-cart');
        const messageContainer = document.getElementById('message-container');
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        const paymentChannelRadios = document.querySelectorAll('input[name="payment_channel"]');
        const digitalOptions = document.getElementById('digital-options');
        const qrContainer = document.getElementById('qr-container');
        const qrImage = document.getElementById('qr-image');
        const qrModal = document.getElementById('qr-modal');
        const qrModalImg = document.getElementById('qr-modal-img');
        const qrModalClose = document.getElementById('qr-modal-close');
        const paymentProofInput = document.getElementById('payment_proof');

        let cart = JSON.parse(localStorage.getItem('cart')) || {};


        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('buynow') === '1') {
            const buyNowData = JSON.parse(localStorage.getItem('buyNowData') || 'null');
            if (buyNowData) {

                cart = {};
                const product = buyNowData.product;
                const key = String(product.id);
                cart[key] = {
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    image: product.image,
                    description: product.description,
                    qty: buyNowData.quantity
                };
                

                const pm = buyNowData.paymentMethod || 'wallet';
                const pc = buyNowData.paymentChannel || 'gcash';
                const pmRadio = document.querySelector(`input[name="payment_method"][value="${pm}"]`);
                if (pmRadio) pmRadio.checked = true;
                const pcRadio = document.querySelector(`input[name="payment_channel"][value="${pc}"]`);
                if (pcRadio) pcRadio.checked = true;
                togglePaymentUI();
                updateQRImage();
                checkWalletBalance();
                

                localStorage.removeItem('buyNowData');
                

                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }


        function updateCartBadge() {
            const totalQty = Object.values(cart).reduce((sum, item) => sum + item.qty, 0);
            localStorage.setItem('cart', JSON.stringify(cart));
            const cartCountEl = document.getElementById('cart-count');
            if (cartCountEl) cartCountEl.textContent = totalQty;
        }


        function renderCart() {
            const items = Object.values(cart);
            cartListEl.innerHTML = '';

            if (items.length === 0) {
                cartListEl.appendChild(emptyCart.cloneNode(true));
                if (cartTotalEl) {
                    cartTotalEl.innerHTML = `
                        <div style="font-size: 13px; color: #666; line-height: 1.6;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Subtotal:</span>
                                <span>₱0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: #FF8B54; font-weight: 600; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                                <span>🚚 Shipping Fee:</span>
                                <span>₱58.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: bold; color: #333; margin-top: 8px;">
                                <span>TOTAL:</span>
                                <span style="color: #FF8B54;">₱58.00</span>
                            </div>
                        </div>
                    `;
                }
                if (checkoutBtn) checkoutBtn.disabled = true;
                updateCartBadge();
                return;
            }

            let total = 0;
            items.forEach(it => {
                total += it.price * it.qty;
                const row = document.createElement('div');
                row.className = 'cart-item';
                const imagePath = it.image ? it.image : 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text x="50" y="50" text-anchor="middle" dy=".3em" font-size="40">🍽️</text></svg>';
                row.innerHTML = `
                    <div class="cart-item-image">
                        <img src="${imagePath}" alt="${it.name}" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text x=%2250%22 y=%2250%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2240%22>🍽️</text></svg>'">
                    </div>
                    <div class="name">
                        ${it.name}
                        <small>₱${it.price.toFixed(2)}</small>
                    </div>
                    <div class="qty">
                        <button data-id="${it.id}" class="dec">−</button>
                        <div>${it.qty}</div>
                        <button data-id="${it.id}" class="inc">+</button>
                    </div>
                    <div style="text-align: right; font-weight: bold;">₱${(it.price * it.qty).toFixed(2)}</div>
                `;
                cartListEl.appendChild(row);
            });


            cartListEl.querySelectorAll('.dec').forEach(b => {
                b.addEventListener('click', () => changeQty(Number(b.dataset.id), -1));
            });
            cartListEl.querySelectorAll('.inc').forEach(b => {
                b.addEventListener('click', () => changeQty(Number(b.dataset.id), +1));
            });


            const shippingFee = 58;
            const grandTotal = total + shippingFee;
            
            if (cartTotalEl) {
                cartTotalEl.innerHTML = `
                    <div style="font-size: 13px; color: #666; line-height: 1.6;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Subtotal:</span>
                            <span>₱${total.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; color: #FF8B54; font-weight: 600; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                            <span>🚚 Shipping Fee:</span>
                            <span>₱${shippingFee.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: bold; color: #333; margin-top: 8px;">
                            <span>TOTAL:</span>
                            <span style="color: #FF8B54;">₱${grandTotal.toFixed(2)}</span>
                        </div>
                    </div>
                `;
            }
            
            if (checkoutBtn) checkoutBtn.disabled = false;

            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartBadge();
            

            if (typeof checkWalletBalance === 'function') {
                checkWalletBalance();
            }
        }


        function changeQty(id, delta) {
            const key = String(id);
            if (!cart[key]) return;
            cart[key].qty += delta;
            if (cart[key].qty <= 0) delete cart[key];
            renderCart();
        }


        if (clearBtn) {
            clearBtn.addEventListener('click', () => {

                fetch('api/clear_cart.php', { method: 'POST', credentials: 'same-origin' })
                .then(r => r.ok ? r.json() : Promise.reject('server'))
                .then(data => {

                    cart = {};
                    renderCart();

                    if (typeof showMessage === 'function') {
                        showMessage('✅ Cart cleared', 'success');
                    } else {
                        const msg = document.createElement('div');
                        msg.textContent = '✅ Cart cleared';
                        msg.style.cssText = 'position:fixed;top:20px;right:20px;background:#4CAF50;color:#fff;padding:10px;border-radius:8px;z-index:9999;';
                        document.body.appendChild(msg);
                        setTimeout(() => msg.remove(), 3000);
                    }
                })
                .catch(err => {
                    if (typeof showMessage === 'function') showMessage('❌ Failed to clear cart', 'error');
                });
            });
        }


        const walletWarning = document.getElementById('wallet-warning');
        const walletBalance = <?= $wallet_balance ?>;

        function checkWalletBalance() {
            const items = Object.values(cart);
            if (items.length === 0) return;

            let subtotal = items.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const shippingFee = 58;
            const grandTotal = subtotal + shippingFee;

            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            if (paymentMethod === 'wallet') {
                if (walletBalance < grandTotal) {
                    walletWarning.style.display = 'block';
                    checkoutBtn.disabled = true;
                } else {
                    walletWarning.style.display = 'none';
                    checkoutBtn.disabled = false;
                }
            } else {
                walletWarning.style.display = 'none';
                checkoutBtn.disabled = false;
            }
        }

        function togglePaymentUI() {
            const selected = document.querySelector('input[name="payment_method"]:checked')?.value;
            if (!selected) return;
            if (digitalOptions) digitalOptions.style.display = selected === 'digital' ? 'block' : 'none';
            if (qrContainer) qrContainer.style.display = selected === 'digital' ? 'block' : 'none';
            if (paymentProofInput) paymentProofInput.required = (selected === 'digital');
            if (selected !== 'wallet' && walletWarning) walletWarning.style.display = 'none';
        }

        function updateQRImage() {
            const channel = document.querySelector('input[name="payment_channel"]:checked')?.value;
            if (!qrImage) return;
            const src = (channel === 'gcash' || !channel) ? 'images/gcash-qr.png' : 'images/bank-qr.png';
            qrImage.src = src;
            if (qrModalImg) qrModalImg.src = src;
        }

        if (qrImage && qrModal) {
            qrImage.addEventListener('click', () => {
                qrModal.style.display = 'flex';
                if (qrModalImg) qrModalImg.src = qrImage.src;
            });
        }

        if (qrModalClose && qrModal) {
            qrModalClose.addEventListener('click', () => {
                qrModal.style.display = 'none';
            });
            qrModal.addEventListener('click', (e) => {
                if (e.target === qrModal) qrModal.style.display = 'none';
            });
        }

        paymentRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                togglePaymentUI();
                checkWalletBalance();
            });
        });

        paymentChannelRadios.forEach(radio => {
            radio.addEventListener('change', updateQRImage);
        });

        togglePaymentUI();
        updateQRImage();
        checkWalletBalance();


        document.getElementById('checkout-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const items = Object.values(cart);
            if (items.length === 0) {
                showMessage('❌ Cart is empty', 'error');
                return;
            }

            const paymentMethodSelected = document.querySelector('input[name="payment_method"]:checked').value;
            const paymentChannel = document.querySelector('input[name="payment_channel"]:checked')?.value || '';
            const paymentProofFile = paymentProofInput?.files?.[0] || null;


            if (paymentMethodSelected === 'wallet') {
                const subtotal = items.reduce((sum, item) => sum + (item.price * item.qty), 0);
                const shippingFee = 58;
                const grandTotal = subtotal + shippingFee;

                if (walletBalance < grandTotal) {
                    showMessage('❌ Insufficient wallet balance. Please add money to your wallet or choose COD.', 'error');
                    return;
                }
            }

            if (paymentMethodSelected === 'digital' && !paymentProofFile) {
                showMessage('❌ Please upload payment proof (screenshot) for Digital Wallet.', 'error');
                return;
            }


            const formData = new FormData();
            formData.append('delivery_address', document.getElementById('delivery_address').value);
            formData.append('payment_method', paymentMethodSelected);
            formData.append('payment_channel', paymentChannel);
            formData.append('items', JSON.stringify(items.map(item => ({
                menu_item_id: item.id,
                price: item.price,
                quantity: item.qty
            }))));

            const subtotal = items.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const shippingFee = 58;
            const grandTotal = subtotal + shippingFee;
            formData.append('total_amount', grandTotal.toFixed(2));

            if (paymentProofFile) {
                formData.append('payment_proof', paymentProofFile);
            }

            checkoutBtn.disabled = true;
            checkoutBtn.textContent = 'Processing...';
            showMessage('Processing your order...', 'info');

            try {
                const response = await fetch('checkout.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showMessage('✅ ' + result.message, 'success');
                    setTimeout(() => {
                        cart = {};
                        renderCart();
                        window.location.href = 'customer_profile.php';
                    }, 1500);
                } else {
                    showMessage('❌ ' + result.message, 'error');
                }
            } catch (error) {
                showMessage('❌ Error: ' + error.message, 'error');
            } finally {
                checkoutBtn.textContent = 'Place Order Now';
                if (Object.values(cart).length > 0) {
                    checkoutBtn.disabled = false;
                }
            }
        });

        function showMessage(msg, type = 'success') {
            messageContainer.innerHTML = `<div class="message ${type}">${msg}</div>`;
            setTimeout(() => {
                messageContainer.innerHTML = '';
            }, 5000);
        }


        renderCart();


        window.addEventListener('storage', () => {
            cart = JSON.parse(localStorage.getItem('cart')) || {};
            renderCart();
        });
        </script>
        <script>
        (function(){
            function applySiteMeta(meta){
                if(!meta) return;
                try{
                    const m = typeof meta === 'string' ? JSON.parse(meta) : meta;
                    if(m.site_name) document.querySelectorAll('.site-name').forEach(el=>el.textContent = m.site_name);
                    if(m.site_description) document.querySelectorAll('.site-desc').forEach(el=>el.textContent = m.site_description);
                    if(m.site_name){
                        if(document.title && document.title.indexOf('—')!==-1){
                            const left = document.title.split('—')[0].trim();
                            document.title = left + ' — ' + m.site_name;
                        } else {
                            document.title = m.site_name + ' — ' + document.title;
                        }
                    }
                }catch(e){}
            }
            applySiteMeta(localStorage.getItem('site_meta'));
            window.addEventListener('storage', (e)=>{ if(e.key==='site_meta') applySiteMeta(e.newValue); });
        })();
        </script>
</body>
</html>
