// ======= Cart & Menu Logic =======

const bestMenuEl = document.getElementById('best-menu');
const otherMenuEl = document.getElementById('other-menu');
const cartCountEl = document.getElementById('cart-count');

let cart = JSON.parse(localStorage.getItem('cart')) || {};

// Lightweight login check for this standalone script
async function ensureLoggedIn() {
  try {
    const res = await fetch('api/login_check.php', { credentials: 'same-origin' });
    const data = await res.json();
    if (data.logged_in) return true;
  } catch (e) {
    console.error('Login check failed', e);
  }
  alert('Please log in first to add items to the cart.');
  window.location.href = 'main/login.html';
  return false;
}

// Sample menu data - Philippine Cuisine
const menuData = [
  // Pork
  { id: 1, name: 'Pork Adobo', desc: 'Tender pork stewed in vinegar and soy sauce', price: 149, category: 'Pork', best: true },
  { id: 2, name: 'Pork Menudo', desc: 'Savory pork and liver stew with vegetables', price: 139, category: 'Pork', best: true },
  { id: 3, name: 'Pork Giniling', desc: 'Ground pork with potatoes and peas', price: 119, category: 'Pork', best: true },
  { id: 4, name: 'Pork Sinigang', desc: 'Sour pork soup with tamarind and vegetables', price: 129, category: 'Pork', best: false },
  
  // Chicken
  { id: 5, name: 'Chicken Adobo', desc: 'Tender chicken in rich brown sauce', price: 139, category: 'Chicken', best: true },
  { id: 6, name: 'Fried Chicken', desc: 'Crispy fried chicken, golden brown', price: 159, category: 'Chicken', best: true },
  { id: 7, name: 'Tinolang Manok', desc: 'Chicken soup with ginger and papaya', price: 119, category: 'Chicken', best: false },
  { id: 8, name: 'Chicken Afritada', desc: 'Chicken in tomato-based stew', price: 129, category: 'Chicken', best: false },
  { id: 9, name: 'Chicken Curry', desc: 'Creamy coconut milk chicken curry', price: 149, category: 'Chicken', best: false },
  
  // Beef
  { id: 10, name: 'Beef Caldereta', desc: 'Beef in rich tomato-based sauce', price: 169, category: 'Beef', best: true },
  { id: 11, name: 'Beef Pares', desc: 'Braised beef with brown gravy', price: 119, category: 'Beef', best: false },
  { id: 12, name: 'Beef Nilaga / Bulalo', desc: 'Beef boiled with potatoes and vegetables', price: 139, category: 'Beef', best: false },
  { id: 13, name: 'Beef Guisado', desc: 'Stewed beef with soy sauce', price: 129, category: 'Beef', best: false },
  
  // Silog Meals
  { id: 14, name: 'Tapsilog', desc: 'Dried beef, fried rice, and egg', price: 149, category: 'Silog Meals', best: true },
  { id: 15, name: 'Tocilog', desc: 'Sweet pork tocino, fried rice, and egg', price: 139, category: 'Silog Meals', best: true },
  { id: 16, name: 'Longsilog', desc: 'Longganisa, fried rice, and egg', price: 129, category: 'Silog Meals', best: true },
  { id: 17, name: 'Hotsilog', desc: 'Hot dog, fried rice, and egg', price: 119, category: 'Silog Meals', best: false },
  { id: 18, name: 'Cornsilog', desc: 'Corned beef, fried rice, and egg', price: 129, category: 'Silog Meals', best: false },
  
  // Vegetables
  { id: 19, name: 'Pinakbet', desc: 'Mixed vegetables in shrimp paste', price: 99, category: 'Vegetables', best: false },
  { id: 20, name: 'Ginisang Ampalaya', desc: 'Sautéed bitter melon with egg', price: 89, category: 'Vegetables', best: false },
  { id: 21, name: 'Monggo', desc: 'Mung bean soup with pork', price: 99, category: 'Vegetables', best: false },
  { id: 22, name: 'Chopsuey', desc: 'Stir-fried mixed vegetables', price: 109, category: 'Vegetables', best: false },
  
  // Fish & Seafood
  { id: 23, name: 'Daing na Bangus', desc: 'Salted milkfish, marinated and fried', price: 179, category: 'Fish & Seafood', best: true },
  { id: 24, name: 'Fried Tilapia', desc: 'Whole fried tilapia with lemon', price: 169, category: 'Fish & Seafood', best: true },
  { id: 25, name: 'Sinigang na Hipon', desc: 'Shrimp in tamarind-based soup', price: 189, category: 'Fish & Seafood', best: false }
];

// ======= Render Menu Items (UPDATED STRUCTURE) =======
function renderMenu() {
  bestMenuEl.innerHTML = '';
  otherMenuEl.innerHTML = '';

  menuData.forEach(item => {
    const card = document.createElement('div');
    card.className = 'card';
    card.innerHTML = `
      <div class="float-wrap">
        <div class="food-img">${item.name.split(' ')[0]}</div>
        <div>
          <h3 class="food-title">${item.name}</h3>
          <p class="food-desc">${item.desc}</p>
        </div>
        
        <div class="card-row">
          <div class="price">₱${item.price.toFixed(2)}</div>
          <div class="card-actions">
            <button class="btn buy-now" data-id="${item.id}">Buy Now</button>
            <button class="btn add-to-cart" data-id="${item.id}">Add</button>
          </div>
        </div>
      </div>
    `;

    if (item.best) bestMenuEl.appendChild(card);
    else otherMenuEl.appendChild(card);
  });

  // Add event listeners for "Add" buttons
  document.querySelectorAll('button.add-to-cart').forEach(b => {
    b.addEventListener('click', () => {
      const id = Number(b.dataset.id);
      addToCart(id);
    });
  });

  // Add event listeners for "Buy Now" buttons
  document.querySelectorAll('button.buy-now').forEach(b => {
    b.addEventListener('click', () => {
      const id = Number(b.dataset.id);
      buyNow(id); 
    });
  });
}

// ======= Add Item to Cart =======
async function addToCart(id) {
  const ok = await ensureLoggedIn();
  if (!ok) return;
  const item = menuData.find(i => i.id === id);
  if (!item) return;
  if (cart[id]) cart[id].qty++;
  else cart[id] = { ...item, qty: 1 };
  saveCart();
  updateCartCount();
}

// ======= New Buy Now Logic =======
function buyNow(id) {
    // 1. Clear the current cart
    resetCart();

    // 2. Add only the selected item
    addToCart(id);

    // 3. Redirect to cart page
    window.location.href = 'main/cart.html'; 
}

// ======= Update Cart Badge =======
function updateCartCount() {
  // Always read from localStorage to sync across pages
  cart = JSON.parse(localStorage.getItem('cart')) || {};
  const totalQty = Object.values(cart).reduce((sum, item) => sum + item.qty, 0);
  if (cartCountEl) cartCountEl.textContent = totalQty;
}

// ======= Save Cart to LocalStorage =======
function saveCart() {
  localStorage.setItem('cart', JSON.stringify(cart));
}

// ======= Reset Cart (for Buy Now or Clear) =======
function resetCart() {
  cart = {};
  saveCart();
  updateCartCount();
}

// ======= Initialize =======
renderMenu();
updateCartCount();

// ======= Listen for storage events (optional) =======
window.addEventListener('storage', () => {
  updateCartCount();
});