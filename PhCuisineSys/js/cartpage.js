

const cartListEl = document.getElementById('cart-list');
const cartTotalEl = document.getElementById('cart-total')?.children[1];
const checkoutBtn = document.getElementById('checkout'); 
const clearBtn = document.getElementById('clear'); 
const checkoutForm = document.getElementById('checkout-form'); 
const messageEl = document.getElementById('message'); 
const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
const paymentChannelRadios = document.querySelectorAll('input[name="payment_channel"]');
const digitalOptions = document.getElementById('digital-options');
const qrContainer = document.getElementById('qr-container');
const qrImage = document.getElementById('qr-image');
const paymentProofInput = document.getElementById('payment_proof');

let cart = JSON.parse(localStorage.getItem('cart')) || {};


function showMessage(message, type = 'info') {
  if (!messageEl) return;
  
  messageEl.innerHTML = message;
  messageEl.style.display = 'block';
  messageEl.className = 'message ' + type; 
  messageEl.style.animation = 'fadeIn 0.3s ease-in';
  
  if (type === 'success' || type === 'info') {
    setTimeout(() => {
      messageEl.style.opacity = '0';
      setTimeout(() => messageEl.style.display = 'none', 300);
    }, 4000);
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
    cartListEl.innerHTML = '<div style="color:#555;font-size:14px">Cart is empty 🍔</div>';
    if (cartTotalEl) cartTotalEl.textContent = '₱0.00';

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


  const shipping_fee = 58;
  const subtotal = total;
  const grandTotal = total + shipping_fee;


  cartListEl.querySelectorAll('.dec').forEach(b => {
    b.addEventListener('click', () => changeQty(Number(b.dataset.id), -1));
  });
  cartListEl.querySelectorAll('.inc').forEach(b => {
    b.addEventListener('click', () => changeQty(Number(b.dataset.id), +1));
  });

  if (cartTotalEl) {

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
  

  if (checkoutBtn) checkoutBtn.disabled = false;
  if (checkoutForm) {
    checkoutForm.querySelectorAll('input, textarea').forEach(el => el.disabled = false);
  }


  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartBadge();
}


function changeQty(id, delta) {
  if (!cart[id]) return;
  cart[id].qty += delta;
  if (cart[id].qty <= 0) delete cart[id];
  renderCart();
}


if (clearBtn) {
  clearBtn.addEventListener('click', () => {
    cart = {};
    renderCart();
    showMessage('Cart cleared', 'info');
  });
}





if (checkoutForm) {
  

  checkoutForm.addEventListener('submit', async (e) => {
    e.preventDefault(); 

    if (checkoutBtn.disabled || Object.values(cart).length === 0) {
      showMessage('Your cart is empty.', 'error');
      return;
    }


    const delivery_address = document.getElementById('delivery_address')?.value?.trim();
    if (!delivery_address) {
      showMessage('Please enter a delivery address.', 'error');
      return;
    }


    const formData = new FormData(checkoutForm);
    const customer_id = formData.get('customer_id');
    const payment_method = formData.get('payment_method') || 'cod';
    const payment_channel = formData.get('payment_channel') || '';
    const paymentProofFile = document.getElementById('payment_proof')?.files?.[0] || null;


    const cartItems = Object.values(cart);
    const itemsForPHP = cartItems.map(item => ({
        menu_item_id: item.id,
        price: item.price,
        quantity: item.qty
    }));
    const totalAmount = cartItems.reduce((sum, item) => sum + (item.price * item.qty), 0);


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


    if (payment_method === 'digital' && !paymentProofFile) {
      showMessage('Please upload payment proof (screenshot) for Digital Wallet.', 'error');
      checkoutBtn.disabled = false;
      checkoutBtn.textContent = "Place Order Now";
      return;
    }

    checkoutBtn.disabled = true;
    checkoutBtn.textContent = "Processing...";
    showMessage('Processing your order...', 'info');


    try {
      const response = await fetch('../checkout.php', {
        method: 'POST',
        body: payload
      });

      const result = await response.json();

      if (result.success) {
        showMessage('✅ Order placed successfully! Order #' + result.order_number, 'success');
        

        setTimeout(() => {
          cart = {}; 
          renderCart();

          window.location.href = '../index.php';
        }, 2000);
        
      } else {
        showMessage('❌ Failed: ' + result.message, 'error');
      }
    } catch (error) {
      console.error('Submission Error:', error);
      showMessage('❌ Connection error. Please try again.', 'error');
    } finally {

      checkoutBtn.textContent = "Place Order Now";
      if (Object.values(cart).length > 0) {
        checkoutBtn.disabled = false;
      }
    }
  });
}




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
      qrImage.src = 'images/bank-qr.png'; 
    }
  }));
}



renderCart();


window.addEventListener('storage', () => {
  cart = JSON.parse(localStorage.getItem('cart')) || {};
  renderCart();
});