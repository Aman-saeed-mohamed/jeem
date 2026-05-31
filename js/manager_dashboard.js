/* 
   Manager Dashboard Logic 
   Handles routing, modals, and internal data rendering.
*/

// --- Mock Data ---
let categories = ["Electronics", "Clothing", "Home & Kitchen", "Footwear"];

let products = [
    { id: 1, name: "Wireless Earbuds pro", category: "Electronics", price: 89.99, image: "https://images.unsplash.com/photo-1590658268037-6f16144e59db?auto=format&fit=crop&w=300&q=80" },
    { id: 2, name: "Premium Cotton T-Shirt", category: "Clothing", price: 24.50, image: "https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=300&q=80" },
    { id: 3, name: "Smart Watch Series 8", category: "Electronics", price: 299.00, image: "https://images.unsplash.com/photo-1546868871-7041f2a55e12?auto=format&fit=crop&w=300&q=80" },
    { id: 4, name: "Running Shoes Sneakers", category: "Footwear", price: 120.00, image: "https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=300&q=80" }
];

let orders = [
    { id: "ORD-1001", customer: "Alice Johnson", address: "123 Main St, NY", items: ["Smart Watch Series 8", "Premium Cotton T-Shirt"], total: 323.50, status: "pending" },
    { id: "ORD-1002", customer: "Bob Smith", address: "456 Oak Ave, CA", items: ["Wireless Earbuds pro"], total: 89.99, status: "pending" },
    { id: "ORD-1003", customer: "Charlie Davis", address: "789 Pine Rd, TX", items: ["Running Shoes Sneakers", "Wireless Earbuds pro"], total: 209.99, status: "accepted" }
];

let deliveries = [
    { orderId: "ORD-1003", customer: "Charlie Davis", address: "789 Pine Rd, TX", items: ["Running Shoes Sneakers", "Wireless Earbuds pro"], total: 209.99, status: "in-transit" }
];

// --- Initialization ---
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initModals();
    initChart();
    
    // Initial renders
    renderProducts();
    renderOrders();
    renderDeliveries();
    populateCategorySelects();
});

// --- Navigation Area (SPA Routing) ---
function initNavigation() {
    const navItems = document.querySelectorAll('.nav-links .nav-item[data-target]');
    const sections = document.querySelectorAll('.page-section');

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            // Remove active classes
            navItems.forEach(nav => nav.classList.remove('active'));
            sections.forEach(sec => sec.classList.remove('active'));
            
            // Add active to clicked nav and target section
            item.classList.add('active');
            const targetId = item.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });
}

// --- Modals Area ---
function initModals() {
    setupModal('categoryModal', 'addCategoryBtn');
    setupModal('productModal', 'addProductBtn');

    // Category Form Submit
    document.getElementById('categoryForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const catName = document.getElementById('categoryName').value.trim();
        if(catName) {
            categories.push(catName);
            populateCategorySelects();
            closeModal('categoryModal');
            document.getElementById('categoryForm').reset();
            showToast('Category added successfully!', 'success');
        }
    });

    // Product Form Submit
    document.getElementById('productForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const idInput = document.getElementById('productId').value;
        const name = document.getElementById('productName').value;
        const category = document.getElementById('productCategory').value;
        const price = parseFloat(document.getElementById('productPrice').value);
        const image = document.getElementById('productImage').value;

        if (idInput) {
            // Update
            const index = products.findIndex(p => p.id == idInput);
            if(index !== -1) {
                products[index] = { ...products[index], name, category, price, image };
                showToast('Product updated successfully!', 'success');
            }
        } else {
            // Create
            const newProduct = {
                id: Date.now(),
                name,
                category,
                price,
                image
            };
            products.push(newProduct);
            showToast('Product added successfully!', 'success');
        }
        
        closeModal('productModal');
        document.getElementById('productForm').reset();
        document.getElementById('productId').value = '';
        renderProducts();
    });
}

function setupModal(modalId, triggerId) {
    const modal = document.getElementById(modalId);
    const trigger = document.getElementById(triggerId);
    const closeBtns = modal.querySelectorAll('.close-btn, .cancel-modal');

    trigger.addEventListener('click', () => {
        if(modalId === 'productModal') {
            document.getElementById('productModalTitle').textContent = 'Add New Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
        }
        modal.classList.add('active');
    });

    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => closeModal(modalId));
    });

    // Close on outside click
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal(modalId);
        }
    });
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// --- Data Rendering Area ---

function populateCategorySelects() {
    const filtersSelect = document.getElementById('categoryFilter');
    const formSelect = document.getElementById('productCategory');
    
    // Save current values if needed, otherwise rebuild
    filtersSelect.innerHTML = '<option value="all">All Categories</option>';
    formSelect.innerHTML = '';

    categories.forEach(cat => {
        filtersSelect.innerHTML += `<option value="${cat}">${cat}</option>`;
        formSelect.innerHTML += `<option value="${cat}">${cat}</option>`;
    });
}

// Render Products
function renderProducts() {
    const grid = document.getElementById('productsGrid');
    grid.innerHTML = '';

    products.forEach(product => {
        const card = document.createElement('div');
        card.className = 'product-card glass-panel';
        card.innerHTML = `
            <img src="${product.image}" alt="${product.name}" class="product-img">
            <div class="product-info">
                <span class="product-category">${product.category}</span>
                <h3 class="product-title">${product.name}</h3>
                <div class="product-price">$${product.price.toFixed(2)}</div>
                <div class="product-actions">
                    <button class="btn btn-outline" onclick="editProduct(${product.id})"><i class="fa-solid fa-pen"></i> Edit</button>
                    <button class="btn btn-danger" onclick="deleteProduct(${product.id})"><i class="fa-solid fa-trash"></i> Delete</button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

function editProduct(id) {
    const product = products.find(p => p.id === id);
    if(product) {
        document.getElementById('productId').value = product.id;
        document.getElementById('productName').value = product.name;
        document.getElementById('productCategory').value = product.category;
        document.getElementById('productPrice').value = product.price;
        document.getElementById('productImage').value = product.image;
        document.getElementById('productModalTitle').textContent = 'Edit Product';
        document.getElementById('productModal').classList.add('active');
    }
}

function deleteProduct(id) {
    if(confirm("Are you sure you want to delete this product?")) {
        products = products.filter(p => p.id !== id);
        renderProducts();
        showToast('Product deleted.', 'error');
    }
}

// Render Orders
function renderOrders() {
    const tbody = document.getElementById('ordersTableBody');
    tbody.innerHTML = '';

    orders.forEach(order => {
        const tr = document.createElement('tr');
        
        // Badge color logic
        let badgeClass = 'status-pending';
        let statusText = 'Pending';
        if(order.status === 'accepted') { badgeClass = 'status-accepted'; statusText = 'Accepted'; }
        else if(order.status === 'rejected') { badgeClass = 'status-rejected'; statusText = 'Rejected'; }

        tr.innerHTML = `
            <td><strong>${order.id}</strong></td>
            <td>
                <div>${order.customer}</div>
                <div class="text-muted" style="font-size:0.8rem">${order.address}</div>
            </td>
            <td>
                <div>${order.items.length} items</div>
                <div class="item-list">${order.items.join(', ')}</div>
            </td>
            <td><strong>$${order.total.toFixed(2)}</strong></td>
            <td><span class="status-badge ${badgeClass}">${statusText}</span></td>
            <td>
                ${order.status === 'pending' ? `
                <div class="table-actions">
                    <button class="btn btn-success" onclick="acceptOrder('${order.id}')" title="Accept"><i class="fa-solid fa-check"></i></button>
                    <button class="btn btn-danger" onclick="rejectOrder('${order.id}')" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                </div>
                ` : `<span class="text-muted">No actions</span>`}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function acceptOrder(id) {
    const orderIndex = orders.findIndex(o => o.id === id);
    if(orderIndex !== -1) {
        orders[orderIndex].status = 'accepted';
        // Add to deliveries
        deliveries.push({
            orderId: orders[orderIndex].id,
            customer: orders[orderIndex].customer,
            address: orders[orderIndex].address,
            items: orders[orderIndex].items,
            total: orders[orderIndex].total,
            status: "pending-delivery"
        });
        renderOrders();
        renderDeliveries();
        showToast(`Order ${id} accepted! Sent to deliveries.`, 'success');
    }
}

function rejectOrder(id) {
    const orderIndex = orders.findIndex(o => o.id === id);
    if(orderIndex !== -1) {
        orders[orderIndex].status = 'rejected';
        renderOrders();
        showToast(`Order ${id} rejected.`, 'error');
    }
}

// Render Deliveries
function renderDeliveries() {
    const tbody = document.getElementById('deliveriesTableBody');
    tbody.innerHTML = '';

    deliveries.forEach(del => {
        const tr = document.createElement('tr');
        
        let badgeClass = 'status-pending';
        let statusText = 'Pending';
        if(del.status === 'in-transit') { badgeClass = 'status-pending'; statusText = 'In Transit'; }
        else if(del.status === 'delivered') { badgeClass = 'status-delivered'; statusText = 'Delivered'; }
        else if(del.status === 'cancelled') { badgeClass = 'status-rejected'; statusText = 'Cancelled'; }
        else if(del.status === 'pending-delivery') { badgeClass = 'status-warning'; statusText = 'Awaiting Dispatch';}

        tr.innerHTML = `
            <td><strong>${del.orderId}</strong></td>
            <td>
                <div>${del.customer}</div>
                <div class="item-list">${del.items.length} items</div>
            </td>
            <td>${del.address}</td>
            <td><strong>$${del.total.toFixed(2)}</strong></td>
            <td><span class="status-badge ${badgeClass}">${statusText}</span></td>
            <td>
                ${(del.status !== 'delivered' && del.status !== 'cancelled') ? `
                <div class="table-actions">
                    <button class="btn btn-primary" onclick="confirmDelivery('${del.orderId}')" title="Confirm Delivery"><i class="fa-solid fa-check-double"></i></button>
                    <button class="btn btn-danger" onclick="cancelDelivery('${del.orderId}')" title="Cancel Delivery"><i class="fa-solid fa-ban"></i></button>
                </div>
                ` : `<span class="text-muted" style="cursor: not-allowed;"><i class="fa-solid fa-lock"></i> Locked</span>`}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function confirmDelivery(orderId) {
    const delIndex = deliveries.findIndex(d => d.orderId === orderId);
    if(delIndex !== -1) {
        deliveries[delIndex].status = 'delivered';
        renderDeliveries();
        showToast(`Delivery for ${orderId} confirmed!`, 'success');
    }
}

function cancelDelivery(orderId) {
    const delIndex = deliveries.findIndex(d => d.orderId === orderId);
    if(delIndex !== -1) {
        if(confirm("Are you sure you want to cancel this delivery?")) {
            deliveries[delIndex].status = 'cancelled';
            renderDeliveries();
            showToast(`Delivery for ${orderId} cancelled.`, 'error');
        }
    }
}

// --- Utils Area ---
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation';
    
    toast.innerHTML = `
        <i class="fa-solid ${icon}"></i>
        <div>${message}</div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s forwards reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// --- Chart setup ---
function initChart() {
    const ctx = document.getElementById('salesChart');
    if(!ctx) return;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Sales Revenue ($)',
                data: [1200, 1900, 1500, 2200, 1800, 2800, 3100],
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#6366f1',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#6366f1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#94a3b8' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                }
            }
        }
    });
}
