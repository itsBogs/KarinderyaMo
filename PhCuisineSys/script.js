
const toggleBtn = document.getElementById('toggleSidebarBtn');
const app = document.querySelector('.app');

toggleBtn.addEventListener('click', () => {
  app.classList.toggle('collapsed');
});

document.addEventListener("DOMContentLoaded", function() {
  const mainContent = document.getElementById("mainContent");
  const menuItems = document.querySelectorAll(".menu-item");


  menuItems.forEach(item => {
    item.addEventListener("click", function() {
      const section = this.dataset.section;


      menuItems.forEach(m => m.classList.remove("active"));
      this.classList.add("active");


      switch (section) {
        case "orders":
          loadSection("order.php");
          break;
        case "dashboard":
          mainContent.innerHTML = document.querySelector('.grid').outerHTML;
          break;
        default:
          mainContent.innerHTML = `<div class='p-4'><h2>${section}</h2><p>Section under construction.</p></div>`;
      }
    });
  });


  function loadSection(url) {
    fetch(url)
      .then(res => res.text())
      .then(html => {
        mainContent.innerHTML = html;
        bindOrderButtons(); 
      })
      .catch(err => {
        mainContent.innerHTML = `<div class='alert alert-danger'>Failed to load ${url}</div>`;
        console.error("Error loading section:", err);
      });
  }


  function bindOrderButtons() {
    const orderBtns = mainContent.querySelectorAll('.mark-delivered');
    orderBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const orderId = this.dataset.id;

        const row = this.closest('tr');
        row.querySelector('.status').textContent = 'Delivered';
        this.disabled = true;
        this.textContent = 'Delivered';
        this.classList.remove('btn-success');
        this.classList.add('btn-secondary');


        console.log('Order marked delivered: ', orderId);
      });
    });
  }

});
