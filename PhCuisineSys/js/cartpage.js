// ======= Cart Page Logic =======

const cartListEl = document.getElementById('cart-list');
const cartTotalEl = document.getElementById('cart-total')?.children[1];
const checkoutBtn = document.getElementById('checkout'); 
const clearBtn = document.getElementById('clear'); 
const checkoutForm = document.getElementById('checkout-form'); // Reference sa bagong form
const messageEl = document.getElementById('message'); // For status messages
const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
const paymentChannelRadios = document.querySelectorAll('input[name="payment_channel"]');
const digitalOptions = document.getElementById('digital-options');
const qrContainer = document.getElementById('qr-container');
const qrImage = document.getElementById('qr-image');
const paymentProofInput = document.getElementById('payment_proof');

let cart = JSON.parse(localStorage.getItem('cart')) || {};

// ======= Show Message =======
function showMessage(message, type = 'info') {
  if (!messageEl) return;
  
  messageEl.innerHTML = message;
  messageEl.style.display = 'block';
  messageEl.className = 'message ' + type; // info, success, error
  messageEl.style.animation = 'fadeIn 0.3s ease-in';
  
  if (type === 'success' || type === 'info') {
    setTimeout(() => {
      messageEl.style.opacity = '0';
      setTimeout(() => messageEl.style.display = 'none', 300);
    }, 4000);
  }
}

// ======= Update Cart Badge =======
function updateCartBadge() {
  const totalQty = Object.values(cart).reduce((sum, item) => sum + item.qty, 0);
  localStorage.setItem('cart', JSON.stringify(cart));
  // Update badge if present
  const cartCountEl = document.getElementById('cart-count');
  if (cartCountEl) cartCountEl.textContent = totalQty;
}

// ======= Render Cart =======
function renderCart() {
  const items = Object.values(cart);
  cartListEl.innerHTML = '';

  if (items.length === 0) {
    cartListEl.innerHTML = '<div style="color:#555;font-size:14px">Cart is empty 🍔</div>';
    if (cartTotalEl) cartTotalEl.textContent = '₱0.00';
    // Disable ang checkout button at form elements
    if (checkoutBtn) checkoutBtn.disabled = true;
    if (checkoutForm) {
      checkoutForm.querySelectorAll('input, textarea').forEach(el => el.disabled = true);
    }
    updateCartBadge();
    return;
  }

  let total = 0;
  items.forEach(it => {
    total += it.price * it.qty;
    const row = document.createElement('div');
    row.className = 'cart-item';
    
    // Get image path - use it.image or fallback to SVG emoji
    const imagePath = it.image ? it.image : 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text x="50" y="50" text-anchor="middle" dy=".3em" font-size="50">🍽️</text></svg>';
    
    row.innerHTML = `
      <div class="cart-item-image">
        <img src="${imagePath}" alt="${it.name}" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text x=%2250%22 y=%2250%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2250%22>🍽️</text></svg>'" loading="lazy">
      </div>
      <div class="name">${it.name}<div style="font-size:12px;color:#666">₱${it.price.toFixed(2)}</div></div>
      <div class="qty">
        <button data-id="${it.id}" class="qty-btn dec">−</button>
        <input type="number" value="${it.qty}" readonly class="qty-input">
        <button data-id="${it.id}" class="qty-btn inc">+</button>
      </div>
      <div style="min-width:70px;text-align:right;font-weight:bold">₱${(it.price * it.qty).toFixed(2)}</div>
    `;
    cartListEl.appendChild(row);
  });

  // Add shipping fee (₱58)
  const shipping_fee = 58;
  const subtotal = total;
  const grandTotal = total + shipping_fee;

  // Attach quantity buttons
  cartListEl.querySelectorAll('.dec').forEach(b => {
    b.addEventListener('click', () => changeQty(Number(b.dataset.id), -1));
  });
  cartListEl.querySelectorAll('.inc').forEach(b => {
    b.addEventListener('click', () => changeQty(Number(b.dataset.id), +1));
  });

  if (cartTotalEl) {
    // Show total with shipping fee breakdown
    cartTotalEl.innerHTML = `
      <div style="font-size: 14px; color: #666; margin-bottom: 8px;">
        <div style="display: flex; justify-content: space-between;">
          <span>Subtotal:</span>
          <span>₱${subtotal.toFixed(2)}</span>
        </div>
        <div style="display: flex; justify-content: space-between; color: var(--accent);">
          <span>Shipping Fee:</span>
          <span>+₱${shipping_fee.toFixed(2)}</span>
        </div>
      </div>
      <div style="font-size: 18px; font-weight: 800; color: var(--strong-accent); border-top: 2px solid var(--accent); padding-top: 8px;">
        Total: ₱${grandTotal.toFixed(2)}
      </div>
    `;
  }
  
  // Enable ang checkout button at form elements kung may laman ang cart
  if (checkoutBtn) checkoutBtn.disabled = false;
  if (checkoutForm) {
    checkoutForm.querySelectorAll('input, textarea').forEach(el => el.disabled = false);
  }

  // Save cart and update badge
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartBadge();
}

// ======= Change Quantity =======
function changeQty(id, delta) {
  if (!cart[id]) return;
  cart[id].qty += delta;
  if (cart[id].qty <= 0) delete cart[id];
  renderCart();
}

// ======= Clear Cart (Existing logic retained) =======
if (clearBtn) {
  clearBtn.addEventListener('click', () => {
    cart = {};
    renderCart();
    showMessage('Cart cleared', 'info');
  });
}

// =======================================================
// ======= NEW: CHECKOUT SUBMISSION LOGIC (AJAX/Fetch) =======
// =======================================================

if (checkoutForm) {
  
  // Event Listener kapag nag-submit ng form (Place Order button)
  checkoutForm.addEventListener('submit', async (e) => {
    e.preventDefault(); // Pipigilan ang default form submission

    if (checkoutBtn.disabled || Object.values(cart).length === 0) {
      showMessage('Your cart is empty.', 'error');
      return;
    }

    // Validate delivery address
    const delivery_address = document.getElementById('delivery_address')?.value?.trim();
    if (!delivery_address) {
      showMessage('Please enter a delivery address.', 'error');
      return;
    }

    // 1. Collect data
    const formData = new FormData(checkoutForm);
    const customer_id = formData.get('customer_id');
    const payment_method = formData.get('payment_method') || 'cod';
    const payment_channel = formData.get('payment_channel') || '';
    const paymentProofFile = document.getElementById('payment_proof')?.files?.[0] || null;

    // 2. Cart items and totals
    const cartItems = Object.values(cart);
    const itemsForPHP = cartItems.map(item => ({
        menu_item_id: item.id,
        price: item.price,
        quantity: item.qty
    }));
    const totalAmount = cartItems.reduce((sum, item) => sum + (item.price * item.qty), 0);

    // 3. Build FormData for multipart (supports file upload)
    const payload = new FormData();
    payload.append('customer_id', customer_id);
    payload.append('delivery_address', delivery_address);
    payload.append('payment_method', payment_method);
    payload.append('payment_channel', payment_channel);
    payload.append('items', JSON.stringify(itemsForPHP));
    payload.append('total_amount', parseFloat(totalAmount.toFixed(2)));
    if (paymentProofFile) {
      payload.append('payment_proof', paymentProofFile);
    }

    // Require proof when digital
    if (payment_method === 'digital' && !paymentProofFile) {
      showMessage('Please upload payment proof (screenshot) for Digital Wallet.', 'error');
      checkoutBtn.disabled = false;
      checkoutBtn.textContent = "Place Order Now";
      return;
    }

    checkoutBtn.disabled = true;
    checkoutBtn.textContent = "Processing...";
    showMessage('Processing your order...', 'info');

    // 4. Send to checkout.php using fetch (multipart)
    try {
      const response = await fetch('../checkout.php', {
        method: 'POST',
        body: payload
      });

      const result = await response.json();

      if (result.success) {
        showMessage('✅ Order placed successfully! Order #' + result.order_number, 'success');
        
        // 4. I-clear ang cart (Local Storage at UI) pagkatapos ng successful order
        setTimeout(() => {
          cart = {}; 
          renderCart();
          // Redirect to menu after 2 seconds
          window.location.href = '../index.php';
        }, 2000);
        
      } else {
        showMessage('❌ Failed: ' + result.message, 'error');
      }
    } catch (error) {
      console.error('Submission Error:', error);
      showMessage('❌ Connection error. Please try again.', 'error');
    } finally {
      // I-enable muli ang button (kung may laman pa rin ang cart)
      checkoutBtn.textContent = "Place Order Now";
      if (Object.values(cart).length > 0) {
        checkoutBtn.disabled = false;
      }
    }
  });
}
// =======================================================


// ======= Payment method & channel toggle =======
function togglePaymentUI() {
  const selected = document.querySelector('input[name="payment_method"]:checked')?.value;
  if (!selected) return;
  if (digitalOptions) digitalOptions.style.display = selected === 'digital' ? 'block' : 'none';
  if (qrContainer) qrContainer.style.display = selected === 'digital' ? 'block' : 'none';
  if (paymentProofInput) paymentProofInput.required = (selected === 'digital');
}

if (paymentRadios && paymentRadios.length) {
  paymentRadios.forEach(r => r.addEventListener('change', togglePaymentUI));
  togglePaymentUI();
}

if (paymentChannelRadios && paymentChannelRadios.length && qrImage) {
  paymentChannelRadios.forEach(r => r.addEventListener('change', () => {
    const channel = document.querySelector('input[name="payment_channel"]:checked')?.value;
    if (channel === 'gcash') {
      qrImage.src = 'images/gcash-qr.png';
    } else {
      qrImage.src = 'images/bank-qr.png'; // placeholder; replace if you have a bank QR image
    }
  }));
}


// ======= Initialize (Existing logic retained) =======
renderCart();

// ======= Sync Across Tabs (Existing logic retained) =======
window.addEventListener('storage', () => {
  cart = JSON.parse(localStorage.getItem('cart')) || {};
  renderCart();
});