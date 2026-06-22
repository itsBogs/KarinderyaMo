<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/settings.php';


$is_logged_in = isset($_SESSION['user_id']);


if ($is_logged_in && in_array($_SESSION['user_role'], ['admin', 'rider', 'owner'])) {
    if ($_SESSION['user_role'] === 'rider') {
        header('Location: rider_panel.php');
    } else {
        header('Location: admin_dashboard.php');
    }
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?> - Food Ordering</title>
    <link rel="stylesheet" href="css/design.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="js/theme-sync.js"></script>
    <style>
        

        
        body {
            background: radial-gradient(circle at top left, var(--bg, #fff7e4) 0%, var(--muted, #f7f1e6) 45%, var(--card, #f8f4ed) 100%);
        }

        
        .login-toast {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 2000;
            display: none;
            align-items: center;
            gap: 10px;
            background: #1f2937;
            color: #f9fafb;
            padding: 10px 14px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            font-size: 13px;
        }
        .login-toast .login-btn {
            background: #f59e0b;
            color: #1f2937;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }
        .login-toast .dismiss-btn {
            background: transparent;
            color: #e5e7eb;
            border: none;
            font-size: 16px;
            cursor: pointer;
        }

        
        .products-container {
            padding: 28px 18px 36px;
            gap: 26px;
        }

        .products-section {
            background: #ffffff;
            border: 1px solid #f0dcc0;
            border-radius: 14px;
            padding: 16px 18px 28px;
            box-shadow: 0 18px 36px rgba(0,0,0,0.06);
        }

        
        header input.search {
            background-color: var(--card); 
            border: 1px solid var(--muted);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: border-color 0.2s;
        }
        header input.search:focus {
            border-color: var(--accent);
            box-shadow: 0 1px 6px rgba(0,0,0,0.1);
        }
        
        
        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--strong-accent); 
            color: var(--card); 
            border-radius: 50%;
            padding: 1px 6px;
            font-size: 10px;
            line-height: 1;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            border: 1px solid var(--card);
        }
        
        
        header .actions .icon-btn {
            position: relative; 
            padding: 10px 14px; 
            font-size: 16px;
        }

        
        .user-info {
            display:flex; 
            align-items:center; 
            gap:10px;
            background-color: var(--accent-2); 
            padding: 8px 12px;
            border-radius: var(--radius);
            color: var(--text); 
            font-weight: 600;
        }
        
        .user-info span {
            font-size: 14px;
            white-space: nowrap;
        }
        
        
        .products-container > .products-section:not(:last-child) {
            border-bottom: 2px dashed var(--muted);
            padding-bottom: 30px;
            margin-bottom: 30px;
        }
        
        
        .products-section .section-title {
            display: block; 
            width: 100%;
            text-align: left;
            padding-left: 5px; 
            margin-bottom: 18px; 
            font-size: 20px;
            color: #b25a00;
            letter-spacing: 0.2px;
        }
        
        .menu.grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 20px; 
            padding: 0;
            overflow-x: unset; 
        }

        
        .menu.grid .card {
            position: relative;
            overflow: hidden;
            transform: translateY(8px);
            opacity: 0;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.25s ease, border-color 0.2s ease;
            background: #ffffff;
            border: 1px solid #f2e6d4;
            box-shadow: 0 8px 18px rgba(0,0,0,0.06);
            border-radius: var(--radius);
        }

        .menu.grid .card:hover {
            transform: translateY(-8px) scale(1.0125);
            box-shadow: 0 22px 34px rgba(0,0,0,0.16);
            border-color: #f0c48c;
        }

        .menu.grid .card:active {
            transform: translateY(-3px) scale(0.995);
            box-shadow: 0 10px 18px rgba(0,0,0,0.14);
            border-color: #e89a3c;
        }

        .card .btn,
        .card .buy-now {
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.2s ease;
        }

        .card .btn:hover,
        .card .buy-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 18px rgba(0,0,0,0.12);
        }

        .card .btn:active,
        .card .buy-now:active {
            transform: translateY(0px) scale(0.99);
            box-shadow: 0 6px 12px rgba(0,0,0,0.12);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        
        @keyframes floaty {
            0% { transform: translateY(0) translateX(0); }
            25% { transform: translateY(-6px) translateX(-2px); }
            50% { transform: translateY(0px) translateX(0); }
            75% { transform: translateY(4px) translateX(2px); }
            100% { transform: translateY(0) translateX(0); }
        }

        .menu.grid .card .float-wrap {
            display: block;
            width: 100%;
            height: 100%;
            animation: floaty 6s ease-in-out infinite;
            will-change: transform;
        }


        
        .product-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .product-preview-modal.active {
            display: flex;
        }

        .product-preview-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .product-preview-image {
            width: 100%;
            height: 300px;
            background: var(--muted);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }

        .product-preview-content h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
            color: var(--strong-accent);
        }

        .product-preview-content p {
            margin: 0 0 15px 0;
            color: #555;
            font-size: 14px;
            line-height: 1.6;
        }

        .product-preview-price {
            font-size: 28px;
            font-weight: bold;
            color: var(--strong-accent);
            margin-bottom: 20px;
        }

        .product-preview-actions {
            display: flex;
            gap: 10px;
        }

        .product-preview-actions button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .product-preview-actions .btn-proceed {
            background: var(--strong-accent);
            color: white;
        }

        .product-preview-actions .btn-proceed:hover {
            background: #c48c07;
        }

        .product-preview-actions .btn-close {
            background: var(--muted);
            color: var(--text);
        }

        .product-preview-actions .btn-close:hover {
            background: #f8d477;
        }

    </style>
</head>
<body>
    <div class="app">
        <div id="login-toast" class="login-toast" role="status" aria-live="polite">
            <span class="login-toast-message">Please log in first to place an order.</span>
            <button class="login-btn" onclick="window.location.href='main/login.html'">Login</button>
            <button class="dismiss-btn" onclick="hideLoginToast()">✕</button>
        </div>
        <header>
                <div class="brand">
                <div class="logo" style="background-color: var(--strong-accent); color: var(--card); font-size: 24px;">
                    🍽️
                </div>
                <div>
                    <h1 class="site-name"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></h1>
                    <p class="lead site-desc" style="color: var(--text);"><?php echo htmlspecialchars(get_setting('site_description', 'Authentic Pinoy Cuisine')); ?></p>
                </div>
            </div>

            <div class="actions">
                <input class="search" placeholder="Search meals, e.g. Adobo" id="search-input" />

                <a href="<?php echo $is_logged_in ? 'cart.php' : 'main/login.html'; ?>" class="icon-btn" title="Go to Cart">
                    🛒
                    <span id="cart-count" class="cart-count">0</span>
                </a>

            <?php if ($is_logged_in): ?>
                <div class="user-info">
                    <span>👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="customer_wallet.php" class="icon-btn" title="My Wallet" style="background-color: #28a745; padding: 8px; border:none;">💰My Wallet</a>
                    <a href="customer_profile.php" class="icon-btn" title="My Orders" style="background-color: var(--accent); padding: 8px; border:none;">📦My Orders</a>
                    <a href="api/logout.php" class="icon-btn" title="Logout" style="background-color: var(--strong-accent); padding: 8px; border:none;">🚪Logout</a>
                </div>
            <?php else: ?>
                <a href="main/login.html" class="btn buy-now" title="Login" style="padding: 10px 18px;">
                    <span style="margin-right: 5px;">🔓</span> Login / Register
                </a>
            <?php endif; ?>

            </div>
        </header>

        <section class="page-title" style="padding: 8px 12px; background: linear-gradient(180deg, var(--bg) 0%, var(--muted) 100%);">

            <h2 style="font-size: 48px; color: var(--strong-accent); margin-bottom: 10px; text-shadow: 1px 1px 2px rgba(0,0,0,0.1);">
                Sarap ng Pagkaing Pinoy!
            </h2>
            <p class="subtitle" style="font-size: 18px; color: var(--text);">
                Tuklasin ang pinakamasarap na pagkaing Pinoy at i-order na! Mabilis, Mura, Masarap.
            </p>
        </section>

<main class="products-container" id="products-container">
    
</main>

        <footer>
            <span class="site-name"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></span> - <?php echo htmlspecialchars(get_setting('site_description', 'Lasapin ang sarap Pinoy!')); ?>
        </footer>
    </div>

    
    <div id="productPreviewModal" class="product-preview-modal">
        <div class="product-preview-content" style="max-width: 1000px; max-height: 90vh; overflow-y: auto; padding: 0; border-radius: 16px; background: white; box-shadow: 0 20px 60px rgba(0,0,0,0.3); display: flex;">
            
            <div style="flex: 0 0 40%; padding: 20px; display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #fff9f7 0%, #fff5f0 100%); border-right: 2px solid #FFD4B8; border-radius: 16px 0 0 16px;">
                <div style="text-align: center; width: 100%;">
                    <div class="product-preview-image" style="margin-bottom: 15px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; height: 200px;">
                        <img id="previewImage" src="" alt="Product" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text x=%2250%22 y=%2250%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2260%22>🍽️</text></svg>'">
                    </div>
                    
                    <h3 id="previewName" style="margin: 0 0 5px 0; color: #333; font-size: 18px; font-weight: 800;">Product Name</h3>
                    <p id="previewDesc" style="margin: 0 0 12px 0; font-size: 12px; color: #666; line-height: 1.5; min-height: 30px;">Product Description</p>
                    
                    <div id="previewPrice" style="font-size: 28px; font-weight: 900; color: #FF8B54; margin-bottom: 16px; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">₱0.00</div>
                    
                    
                    <div style="margin: 0; padding: 14px; background: white; border-radius: 10px; border: 2px solid #FFD4B8; width: 100%;">
                        <label style="display: block; font-weight: 700; margin-bottom: 10px; font-size: 12px; color: #333; text-transform: uppercase; letter-spacing: 0.5px;">📦 Quantity</label>
                        <div style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                            <button type="button" id="qtyMinus" style="width: 34px; height: 34px; border: none; background: linear-gradient(135deg, #FF8B54, #FF6B54); color: white; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 18px; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(255,107,84,0.2);">−</button>
                            <input type="number" id="buyNowQty" value="1" min="1" max="99" style="width: 50px; text-align: center; border: 2px solid #FFD4B8; padding: 6px; border-radius: 8px; font-weight: bold; font-size: 14px; background: white;">
                            <button type="button" id="qtyPlus" style="width: 34px; height: 34px; border: none; background: linear-gradient(135deg, #FF8B54, #FF6B54); color: white; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 18px; transition: all 0.2s ease; box-shadow: 0 2px 6px rgba(255,107,84,0.2);">+</button>
                        </div>
                    </div>

                    
                    <div style="margin: 16px 0 0 0; padding: 14px; background: white; border-radius: 10px; border: 2px solid #FFD4B8; width: 100%;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px; color: #666;">
                            <span style="font-weight: 600;">Unit Price:</span>
                            <span style="font-weight: 600;">₱<span id="unitPrice">0.00</span></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; color: #666;">
                            <span style="font-weight: 600;">Subtotal:</span>
                            <span style="font-weight: 700;">₱<span id="subTotal">0.00</span></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-bottom: 6px; border-bottom: 1px dashed #FFD4B8; font-size: 12px; color: #FF8B54; font-weight: 600;">
                            <span>🚚 Shipping Fee:</span>
                            <span>₱<span id="shipFee">58.00</span></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding-top: 8px; font-size: 16px; font-weight: 900; color: #FF8B54;">
                            <span>Total:</span>
                            <span>₱<span id="totalPrice">0.00</span></span>
                        </div>
                    </div>
                </div>
            </div>

            
            <div style="flex: 0 0 60%; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; border-radius: 0 16px 16px 0; position: relative;">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #FFD4B8;">
                    <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #333;">🛒 Complete Your Order</h2>
                    <button type="button" onclick="closePreview()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">✕</button>
                </div>

                
                <form id="buyNowForm" style="flex: 1; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; padding-right: 8px;">
                    <h4 style="margin: 0 0 8px 0; font-size: 12px; color: #333; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">📍 Delivery Details</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="display: block; margin-bottom: 4px; color: #333; font-weight: 700; font-size: 11px;">Full Name</label>
                            <input type="text" id="buyNowName" required placeholder="Your name" style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 12px; box-sizing: border-box; font-family: inherit; transition: all 0.2s ease;" onfocus="this.style.borderColor='#FF8B54'; this.style.boxShadow='0 0 8px rgba(255,139,84,0.15)';" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 4px; color: #333; font-weight: 700; font-size: 11px;">Phone Number</label>
                            <input type="tel" id="buyNowPhone" required placeholder="Your phone" style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 12px; box-sizing: border-box; font-family: inherit; transition: all 0.2s ease;" onfocus="this.style.borderColor='#FF8B54'; this.style.boxShadow='0 0 8px rgba(255,139,84,0.15)';" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 4px; color: #333; font-weight: 700; font-size: 11px;">Delivery Address</label>
                        <textarea id="buyNowAddress" required placeholder="Enter your address..." style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 11px; box-sizing: border-box; min-height: 50px; font-family: inherit; resize: none; transition: all 0.2s ease;" onfocus="this.style.borderColor='#FF8B54'; this.style.boxShadow='0 0 8px rgba(255,139,84,0.15)';" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';"></textarea>
                    </div>

                    <h4 style="margin: 8px 0 6px 0; font-size: 12px; color: #333; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">💳 Payment Method</h4>
                    
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 6px; border: 2px solid #e8e8e8; display: flex; gap: 12px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; margin: 0; cursor: pointer; gap: 6px; flex: 1;">
                            <input type="radio" name="buyNowPayment" value="wallet" checked style="width: 16px; height: 16px; cursor: pointer; accent-color: #28a745;">
                            <span style="font-size: 11px; font-weight: 600; color: #333;">💰 Wallet</span>
                        </label>

                        <label style="display: flex; align-items: center; margin: 0; cursor: pointer; gap: 6px; flex: 1;">
                            <input type="radio" name="buyNowPayment" value="digital" style="width: 16px; height: 16px; cursor: pointer; accent-color: #3b82f6;">
                            <span style="font-size: 11px; font-weight: 600; color: #333;">💳 Digital Wallet</span>
                        </label>

                        <label style="display: flex; align-items: center; margin: 0; cursor: pointer; gap: 6px; flex: 1;">
                            <input type="radio" name="buyNowPayment" value="cod" style="width: 16px; height: 16px; cursor: pointer; accent-color: #FF8B54;">
                            <span style="font-size: 11px; font-weight: 600; color: #333;">💵 COD</span>
                        </label>
                    </div>

                    <div id="buyNowDigitalOptions" style="display: none; margin-top: 10px; background: #eef3ff; border: 2px solid #c7d5ff; border-radius: 8px; padding: 10px;">
                        <div style="font-size: 11px; font-weight: 700; color: #1f3a8a; margin-bottom: 6px;">Choose channel</div>
                        <label style="display:block; font-size: 11px; font-weight:600; color:#1f3a8a; margin-bottom:4px;">
                            <input type="radio" name="buyNowChannel" value="gcash" checked style="width:14px; height:14px; cursor:pointer; accent-color:#2563eb;"> GCash (Recommended)
                        </label>
                        <label style="display:block; font-size: 11px; font-weight:600; color:#1f3a8a; margin-bottom:8px;">
                            <input type="radio" name="buyNowChannel" value="bank" style="width:14px; height:14px; cursor:pointer; accent-color:#2563eb;"> Bank Transfer
                        </label>
                        <div style="font-size: 10px; color:#1f3a8a; margin-bottom:6px;">Scan and pay, then attach proof before placing the order.</div>
                        <img id="buyNowQRImage" src="images/gcash-qr.png" alt="Payment QR" style="max-width: 240px; width:100%; border:1px solid #d9e3ff; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.06); cursor:pointer;">
                        <div style="margin-top:8px;">
                            <label style="font-size:11px; font-weight:700; color:#1f3a8a; display:block; margin-bottom:4px;">Upload Payment Proof (screenshot)</label>
                            <input type="file" id="buyNowProof" accept="image/*" style="font-size:11px;">
                        </div>
                    </div>

                    <div id="buyNowWalletInfo" style="padding: 10px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 6px; border: 2px solid #28a745; font-size: 11px; font-weight: 600; color: #333;">
                        💰 Wallet Balance: ₱<span id="buyNowWalletBalance">0.00</span>
                    </div>
                    
                    <div id="buyNowWalletWarning" style="display: none; padding: 10px; background: #fff3cd; border-radius: 6px; border: 2px solid #ffc107; font-size: 11px; font-weight: 600; color: #856404;">
                        ⚠️ Insufficient balance. <a href="customer_wallet.php" target="_blank" style="color: #856404; text-decoration: underline;">Add money</a> or choose COD.
                    </div>
                </form>

                <div id="buyNowMessage" style="padding: 10px; margin: 8px 0; border-radius: 6px; display: none; font-size: 11px; font-weight: 600; border: 2px solid; text-align: center;"></div>

                
                <div style="display: flex; gap: 10px; margin-top: 12px;">
                    <button type="button" id="buyNowSubmit" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; border: none; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(76,175,80,0.25); display: flex; align-items: center; justify-content: center; gap: 6px;">✓ Order Now</button>
                    <button type="button" onclick="closePreview()" style="flex: 1; padding: 12px; background: #e0e0e0; color: #333; border: none; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.2s ease;">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>

        let menuData = {};
        let lastMenuSnapshot = '';
        const defaultDescriptions = {
           'Pork Adobo': 'Classic savory-sour pork slowly simmered in soy sauce, vinegar, garlic, bay leaves, and peppercorns, resulting in tender meat with rich, deep flavor.',
    'Pork Menudo': 'Hearty pork stew with liver, potatoes, carrots, hotdog slices, and peas cooked in thick tomato sauce, giving it a slightly sweet fiesta-style taste.',
    'Pork Giniling': 'Ground pork sautéed with carrots, potatoes, and bell peppers in flavorful tomato sauce, making it a comforting everyday ulam served best with steamed rice.',
    'Pork Sinigang': 'Tamarind-sour pork soup with kangkong, gabi, radish, and sitaw, delivering a refreshing asim-sarap classic perfect for rainy days.',
    'Chicken Adobo': 'Tender chicken braised in soy sauce, vinegar, garlic, and spices, absorbing the signature adobo blend for a salty-tangy iconic Filipino taste.',
    'Fried Chicken': 'Crispy, golden-brown chicken fried to perfection with juicy, well-seasoned meat inside—best paired with gravy or ketchup.',
    'Tinolang Manok': 'Light ginger broth with chicken, green papaya, and chili leaves, offering a soothing, aromatic soup ideal for cold weather.',
    'Chicken Afritada': 'Tomato-based chicken stew loaded with potatoes, carrots, and bell peppers, giving a mildly sweet, comforting home-cooked flavor.',
    'Chicken Curry': 'Creamy coconut milk-based chicken curry with mild spice, potatoes, and carrots, delivering rich aroma and smooth texture.',
    'Pinakbet': 'Mixed local veggies like ampalaya, okra, sitaw, and squash sautéed with bagoong, giving a savory, earthy Ilocano-style taste.',
    'Ginisang Ampalaya': 'Stir-fried bitter melon with egg and aromatics, creating a nutritious dish that balances ampalaya’s natural bitterness with soft egg texture.',
    'Monggo': 'Nutritious mung bean stew simmered with pork bits, malunggay leaves, and tinapa flakes for added smoky depth and warmth.',
    'Chopsuey': 'Colorful medley of cabbage, broccoli, carrots, and cauliflower stir-fried in a light, savory sauce with tender meat pieces or shrimp.'
        };
        let cart = JSON.parse(localStorage.getItem('cart')) || {};

        const IS_LOGGED_IN = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        let isLoggedInState = IS_LOGGED_IN;
        const LOGIN_PAGE = 'main/login.html';

        async function loadMenu() {
            try {
                const response = await fetch('api/get_menu.php'); 
                const data = await response.json();

                const snapshot = JSON.stringify(data);
                if (snapshot === lastMenuSnapshot) return; 

                lastMenuSnapshot = snapshot;
                menuData = data;
                renderMenuByCategory();
                applySearchFilter();
            } catch (error) {
                console.error('Error loading menu:', error);
            }
        }

      
        async function ensureLoggedIn() {
            if (isLoggedInState) return true;
            try {
                const res = await fetch('api/login_check.php', { credentials: 'same-origin' });
                const data = await res.json();
                if (data.logged_in) {
                    isLoggedInState = true;
                    return true;
                }
            } catch (err) {
                console.error('Login check failed:', err);
            }
            showLoginToast('Please log in first to place an order.');
            return false;
        }

        function showLoginToast(message) {
            const toast = document.getElementById('login-toast');
            if (!toast) return;
            const msgEl = toast.querySelector('.login-toast-message');
            if (msgEl) msgEl.textContent = message || 'Please log in first to place an order.';
            toast.style.display = 'flex';
            toast.style.animation = 'fadeUp 0.25s ease';
            clearTimeout(showLoginToast._timer);
            showLoginToast._timer = setTimeout(() => hideLoginToast(), 4000);
        }

        function hideLoginToast() {
            const toast = document.getElementById('login-toast');
            if (toast) toast.style.display = 'none';
        }
        
        function generateImagePath(productName) {
            if (!productName) return null;

            const correctedFilename = productName.replace(/\s/g, '-') + '.jpg';
            return `images/${correctedFilename}`;
        }

        function normalizeImagePath(imagePath, productName) {
            let path = imagePath && String(imagePath).trim()
                ? String(imagePath).trim()
                : generateImagePath(productName);

            if (!path) return null;
            if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('//') || path.startsWith('data:')) return path;

            path = path.replace(/\\/g, '/');
            if (path.startsWith('./')) path = path.slice(2);
            path = path.replace(/^\/+/, '');

            return encodeURI(path);
        }
        
        function renderMenuByCategory() {
            const container = document.getElementById('products-container');
            if (!container) return;
            container.innerHTML = '';

            const entries = Object.entries(menuData || {});
            if (!entries.length) {
                container.innerHTML = '<div class="products-section"><div class="text-muted">No menu items found.</div></div>';
                return;
            }

            entries.forEach(([category, items], sectionIdx) => {
                const section = document.createElement('section');
                section.className = 'products-section';
                const safeCat = category || 'Menu';
                section.innerHTML = `
                    <h3 class="section-title">${safeCat}</h3>
                    <div class="menu grid" id="section-${sectionIdx}"></div>
                `;
                container.appendChild(section);

                const element = section.querySelector('.menu.grid');
                (items || []).forEach((item, idx) => {
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.style.animation = `fadeUp 0.45s ease ${idx * 0.05}s both`;

                    const imagePath = normalizeImagePath(item.image, item.name);

                    const imgTag = imagePath
                        ? `<img src="${imagePath}" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><text y=\'.9em\' font-size=\'90\'>🍽️</text></svg>'" alt="${item.name}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius);">`
                        : '🍽️';

                    card.innerHTML = `
                        <div class="float-wrap">
                          <div class="food-img">${imgTag}</div>
                          <h3 class="food-title">${item.name}</h3>
                          <p class="food-desc">${item.description || defaultDescriptions[item.name] || 'Masarap na pagkaing Pinoy.'}</p>
                          <div class="card-row">
                              <div class="food-price">₱${parseFloat(item.price).toFixed(2)}</div>
                              <div class="card-actions">
                                  <button class="btn buy-now" data-id="${item.id}" title="Buy this item instantly">Buy Now</button>
                                  <button class="btn" data-id="${item.id}" title="Add to your cart">Add to Cart</button>
                              </div>
                          </div>
                        </div>
                    `;
                    element.appendChild(card);
                });
            });


            container.querySelectorAll('button.btn').forEach(b => {
                b.addEventListener('click', () => {
                    const id = Number(b.dataset.id);
                    if (b.classList.contains('buy-now')) {
                        buyNow(id);
                    } else {
                        addToCart(id);
                    }
                });
            });

            applySearchFilter();
        }

        async function addToCart(id) {

            const ok = await ensureLoggedIn();
            if (!ok) return;

            let item = null;
            for (const category of Object.keys(menuData || {})) {
                const found = (menuData[category] || []).find(i => i.id === id);
                if (found) { item = found; break; }
            }
            if (!item) return;
            if (cart[id]) cart[id].qty++;
            else cart[id] = { ...item, qty: 1 };
            saveCart();
            updateCartCount();
        }
        

        function resetCart() {
            cart = {};
            saveCart();
            updateCartCount();
        }

        let selectedProductForBuy = null;

        function showProductPreview(id) {
            let item = null;
            for (const category of Object.keys(menuData || {})) {
                const found = (menuData[category] || []).find(i => i.id === id);
                if (found) { item = found; break; }
            }
            if (!item) return;

            selectedProductForBuy = item;
            

            const imagePath = normalizeImagePath(item.image, item.name);
            

            document.getElementById('previewImage').src = imagePath;
            document.getElementById('previewName').textContent = item.name;
            document.getElementById('previewDesc').textContent = item.description || defaultDescriptions[item.name] || 'Masarap na pagkaing Pinoy.';
            document.getElementById('previewPrice').textContent = '₱' + parseFloat(item.price).toFixed(2);
            

            document.getElementById('productPreviewModal').classList.add('active');
        }

        function closePreview() {
            document.getElementById('productPreviewModal').classList.remove('active');
            selectedProductForBuy = null;
        }

        function proceedToCheckout() {
            if (!selectedProductForBuy) return;
            
            const quantity = parseInt(document.getElementById('buyNowQty').value) || 1;
            const paymentMethod = document.querySelector('input[name="buyNowPayment"]:checked').value;
            
            const paymentChannel = document.querySelector('input[name="buyNowChannel"]:checked')?.value || 'gcash';


            localStorage.setItem('buyNowData', JSON.stringify({
                product: selectedProductForBuy,
                quantity: quantity,
                paymentMethod: paymentMethod,
                paymentChannel: paymentChannel
            }));
            
            closePreview();
            window.location.href = 'cart.php?buynow=1';
        }

        async function buyNow(id) {
            const isOk = await ensureLoggedIn();
            if (!isOk) return;

            showProductPreview(id);
            document.getElementById('buyNowQty').value = '1';
            document.querySelector('input[name="buyNowPayment"][value="wallet"]').checked = true;
            const defaultChannel = document.querySelector('input[name="buyNowChannel"][value="gcash"]');
            if (defaultChannel) defaultChannel.checked = true;
            toggleBuyNowPayment();
            updateBuyNowTotal();
            

            fetch('api/get_user_info.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('buyNowName').value = data.user_name || '';
                        document.getElementById('buyNowPhone').value = data.user_phone || '';
                        document.getElementById('buyNowAddress').value = data.user_address || '';
                    }
                })
                .catch(error => console.error('Error fetching user info:', error));
        }


        function updateCartCount() {
            cart = JSON.parse(localStorage.getItem('cart')) || {};
            const totalQty = Object.values(cart).reduce((sum, item) => sum + item.qty, 0);
            document.getElementById('cart-count').textContent = totalQty;
        }

        function saveCart() {
            localStorage.setItem('cart', JSON.stringify(cart));
        }

        function applySearchFilter() {
            const query = (document.getElementById('search-input')?.value || '').toLowerCase();
            const cards = document.querySelectorAll('#products-container .card');
            cards.forEach(card => {
                const title = card.querySelector('.food-title').textContent.toLowerCase();
                card.style.display = title.includes(query) ? '' : 'none';
            });
        }


        document.getElementById('search-input').addEventListener('keyup', applySearchFilter);


        loadMenu();
        setInterval(loadMenu, 1000); 
        updateCartCount();

        window.addEventListener('storage', () => {
            updateCartCount();
        });


        document.getElementById('productPreviewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });


        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });


        document.getElementById('qtyMinus').addEventListener('click', (e) => {
            e.preventDefault();
            const input = document.getElementById('buyNowQty');
            const current = parseInt(input.value) || 1;
            if (current > 1) {
                input.value = current - 1;
                updateBuyNowTotal();
            }
        });

        document.getElementById('qtyPlus').addEventListener('click', (e) => {
            e.preventDefault();
            const input = document.getElementById('buyNowQty');
            const current = parseInt(input.value) || 1;
            if (current < 99) {
                input.value = current + 1;
                updateBuyNowTotal();
            }
        });

        document.getElementById('buyNowQty').addEventListener('change', updateBuyNowTotal);


        function updateBuyNowTotal() {
            if (!selectedProductForBuy) return;
            const qty = parseInt(document.getElementById('buyNowQty').value) || 1;
            const price = parseFloat(selectedProductForBuy.price) || 0;
            const subtotal = price * qty;
            const shippingFee = 58; 
            const grandTotal = subtotal + shippingFee;

            document.getElementById('unitPrice').textContent = price.toFixed(2);
            document.getElementById('subTotal').textContent = subtotal.toFixed(2);
            document.getElementById('shipFee').textContent = shippingFee.toFixed(2);
            document.getElementById('totalPrice').textContent = grandTotal.toFixed(2);
            

            const isWallet = document.querySelector('input[name="buyNowPayment"][value="wallet"]').checked;
            if (isWallet) {
                checkBuyNowWalletBalance();
            }
        }


        function toggleBuyNowPayment() {
            const walletInfo = document.getElementById('buyNowWalletInfo');
            const walletWarning = document.getElementById('buyNowWalletWarning');
            const submitBtn = document.getElementById('buyNowSubmit');
            const digitalOptions = document.getElementById('buyNowDigitalOptions');
            const proofInput = document.getElementById('buyNowProof');
            const isWallet = document.querySelector('input[name="buyNowPayment"][value="wallet"]').checked;
            const isDigital = document.querySelector('input[name="buyNowPayment"][value="digital"]').checked;
            
            if (isWallet) {
                walletInfo.style.display = 'block';
                if (digitalOptions) digitalOptions.style.display = 'none';
                if (proofInput) proofInput.required = false;
                checkBuyNowWalletBalance();
            } else if (isDigital) {
                walletInfo.style.display = 'none';
                walletWarning.style.display = 'none';
                if (digitalOptions) digitalOptions.style.display = 'block';
                if (proofInput) proofInput.required = true;
                if (submitBtn) submitBtn.disabled = false;
            } else {
                walletInfo.style.display = 'none';
                walletWarning.style.display = 'none';
                if (digitalOptions) digitalOptions.style.display = 'none';
                if (proofInput) proofInput.required = false;
                if (submitBtn) submitBtn.disabled = false; 
            }
        }


        function checkBuyNowWalletBalance() {
            if (!selectedProductForBuy) return;
            const isWallet = document.querySelector('input[name="buyNowPayment"][value="wallet"]').checked;
            if (!isWallet) return; 
            
            const quantity = parseInt(document.getElementById('buyNowQty').value) || 1;
            const price = parseFloat(selectedProductForBuy.price);
            const shippingFee = 58;
            const grandTotal = (price * quantity) + shippingFee;
            
            fetch('api/get_wallet_balance.php')
                .then(response => response.json())
                .then(data => {
                    const balance = data.balance || 0;
                    document.getElementById('buyNowWalletBalance').textContent = balance.toFixed(2);
                    
                    const walletWarning = document.getElementById('buyNowWalletWarning');
                    const submitBtn = document.getElementById('buyNowSubmit');
                    
                    if (balance < grandTotal) {
                        walletWarning.style.display = 'block';
                        submitBtn.disabled = true;
                    } else {
                        walletWarning.style.display = 'none';
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error fetching wallet balance:', error);
                    document.getElementById('buyNowWalletBalance').textContent = '0.00';
                });
        }

        document.querySelectorAll('input[name="buyNowPayment"]').forEach(radio => {
            radio.addEventListener('change', toggleBuyNowPayment);
        });


        document.querySelectorAll('input[name="buyNowChannel"]').forEach(radio => {
            radio.addEventListener('change', () => {
                const channel = document.querySelector('input[name="buyNowChannel"]:checked')?.value;
                const qr = document.getElementById('buyNowQRImage');
                if (!qr) return;
                const src = channel === 'gcash' ? 'images/gcash-qr.png' : 'images/bank-qr.png';
                qr.src = src;
            });
        });


        document.getElementById('buyNowQRImage')?.addEventListener('click', () => {
            const src = document.getElementById('buyNowQRImage').src;
            const overlayId = 'buyNowQrOverlay';
            let overlay = document.getElementById(overlayId);
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = overlayId;
                overlay.style.position = 'fixed';
                overlay.style.inset = '0';
                overlay.style.background = 'rgba(0,0,0,0.7)';
                overlay.style.zIndex = '99999';
                overlay.style.display = 'flex';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';
                overlay.innerHTML = `<div style="background:#fff; padding:12px; border-radius:10px; max-width:90vw; max-height:90vh; box-shadow:0 12px 30px rgba(0,0,0,0.25);"><img id="buyNowQrOverlayImg" src="${src}" alt="QR" style="max-width:80vw; max-height:80vh; display:block; margin:0 auto;"></div>`;
                document.body.appendChild(overlay);
                overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
            }
            const img = document.getElementById('buyNowQrOverlayImg');
            if (img) img.src = src;
            overlay.style.display = 'flex';
        });


        document.getElementById('buyNowSubmit').addEventListener('click', async (e) => {
            e.preventDefault();

            if (!selectedProductForBuy) {
                showBuyNowMessage('❌ Product not found', 'error');
                return;
            }

            const ok = await ensureLoggedIn();
            if (!ok) return;


            const name = document.getElementById('buyNowName').value.trim();
            const phone = document.getElementById('buyNowPhone').value.trim();
            const address = document.getElementById('buyNowAddress').value.trim();
            const quantity = parseInt(document.getElementById('buyNowQty').value) || 1;
            const paymentMethod = document.querySelector('input[name="buyNowPayment"]:checked').value;
            const paymentChannel = document.querySelector('input[name="buyNowChannel"]:checked')?.value || '';

            if (!name) {
                showBuyNowMessage('❌ Please enter your name', 'error');
                return;
            }
            if (!phone) {
                showBuyNowMessage('❌ Please enter your phone number', 'error');
                return;
            }
            if (!address) {
                showBuyNowMessage('❌ Please enter your delivery address', 'error');
                return;
            }
            

            if (paymentMethod === 'wallet') {
                const price = parseFloat(selectedProductForBuy.price);
                const shippingFee = 58;
                const grandTotal = (price * quantity) + shippingFee;
                
                try {
                    const walletResponse = await fetch('api/get_wallet_balance.php');
                    const walletData = await walletResponse.json();
                    const balance = walletData.balance || 0;
                    
                    if (balance < grandTotal) {
                        showBuyNowMessage('❌ Insufficient wallet balance. Please add money or choose COD.', 'error');
                        return;
                    }
                } catch (error) {
                    showBuyNowMessage('❌ Could not verify wallet balance', 'error');
                    return;
                }
            }

            const submitBtn = document.getElementById('buyNowSubmit');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Processing...';

            try {
                const price = parseFloat(selectedProductForBuy.price);
                const shippingFee = 58;
                const grandTotal = (price * quantity) + shippingFee;

                if (paymentMethod === 'digital') {
                    const proof = document.getElementById('buyNowProof')?.files?.[0];
                    if (!proof) {
                        showBuyNowMessage('❌ Please upload payment proof for Digital Wallet.', 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span style="margin-right: 4px;">✓</span> Order Now';
                        return;
                    }

                    const formData = new FormData();
                    formData.append('delivery_address', address);
                    formData.append('payment_method', paymentMethod);
                    formData.append('payment_channel', paymentChannel || 'gcash');
                    formData.append('items', JSON.stringify([{
                        menu_item_id: selectedProductForBuy.id,
                        price: price,
                        quantity: quantity
                    }]));
                    formData.append('total_amount', grandTotal.toFixed(2));
                    formData.append('payment_proof', proof);

                    const response = await fetch('checkout.php', {
                        method: 'POST',
                        body: formData
                    });

                    const ct = response.headers.get('content-type') || '';
                    if (!response.ok || !ct.includes('application/json')) {
                        showBuyNowMessage('❌ Please login first.', 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span style="margin-right: 4px;">✓</span> Order Now';
                        return;
                    }

                    const result = await response.json();

                    if (result.success) {
                        showBuyNowMessage('✅ Order placed successfully!', 'success');
                        setTimeout(() => {
                            closePreview();
                            window.location.href = 'customer_profile.php';
                        }, 1500);
                    } else {
                        showBuyNowMessage('❌ ' + (result.message || 'Error placing order'), 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span style="margin-right: 4px;">✓</span> Order Now';
                    }
                    return;
                }

                const response = await fetch('checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        delivery_address: address,
                        payment_method: paymentMethod,
                        items: [{
                            id: selectedProductForBuy.id,
                            name: selectedProductForBuy.name,
                            price: parseFloat(selectedProductForBuy.price),
                            quantity: quantity,
                            menu_item_id: selectedProductForBuy.id,
                            image: selectedProductForBuy.image
                        }]
                    })
                });

                const ct = response.headers.get('content-type') || '';
                if (!response.ok || !ct.includes('application/json')) {
                    showBuyNowMessage('❌ Please login first.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span style="margin-right: 4px;">✓</span> Order Now';
                    return;
                }

                const result = await response.json();

                if (result.success) {
                    showBuyNowMessage('✅ Order placed successfully!', 'success');
                    setTimeout(() => {
                        closePreview();
                        window.location.href = 'customer_profile.php';
                    }, 1500);
                } else {
                    showBuyNowMessage('❌ ' + (result.message || 'Error placing order'), 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span style="margin-right: 4px;">✓</span> Order Now';
                }
            } catch (error) {
                console.error('Error:', error);
                showBuyNowMessage('❌ Error: ' + error.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span style="margin-right: 4px;">✓</span> Order Now';
            }
        });

        function showBuyNowMessage(msg, type) {
            const msgEl = document.getElementById('buyNowMessage');
            msgEl.textContent = msg;
            msgEl.style.display = 'block';
            msgEl.style.background = type === 'success' ? '#d4edda' : '#f8d7da';
            msgEl.style.color = type === 'success' ? '#155724' : '#721c24';
            msgEl.style.border = type === 'success' ? '1px solid #c3e6cb' : '1px solid #f5c6cb';

            if (type === 'success') {
                setTimeout(() => {
                    msgEl.style.display = 'none';
                }, 3000);
            }
        }

    </script>
</body>
</html>
