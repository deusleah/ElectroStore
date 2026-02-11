// API Base URL
const API_URL = 'api';

// Current User State
let currentUser = null;

// Initialize App
document.addEventListener('DOMContentLoaded', function() {
    checkSession();
    updateNav();
});

// Check if user is logged in
async function checkSession() {
    try {
        const response = await fetch(API_URL + 'auth.php?action=check');
        const data = await response.json();
        
        if (data.success && data.logged_in) {
            currentUser = data.user;
            updateNav();
        } else {
            currentUser = null;
            updateNav();
        }
    } catch (error) {
        console.error('Session check error:', error);
    }
}

// Update Navigation based on user state
function updateNav() {
    const userMenu = document.getElementById('userMenu');
    const guestMenu = document.getElementById('guestMenu');
    const adminLink = document.getElementById('adminLink');
    
    if (!userMenu || !guestMenu) return;
    
    if (currentUser) {
        userMenu.classList.remove('hidden');
        guestMenu.classList.add('hidden');
        
        const usernameSpan = document.getElementById('username');
        if (usernameSpan) {
            usernameSpan.textContent = currentUser.username;
        }
        
        if (adminLink) {
            if (currentUser.role === 'admin') {
                adminLink.classList.remove('hidden');
            } else {
                adminLink.classList.add('hidden');
            }
        }
    } else {
        userMenu.classList.add('hidden');
        guestMenu.classList.remove('hidden');
        
        if (adminLink) {
            adminLink.classList.add('hidden');
        }
    }
}

// Login Function
async function login(username, password) {
    try {
        const response = await fetch(API_URL + 'auth.php?action=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({username: username,
                password: password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            updateNav();
            return { success: true, message: data.message };
        } else {
            return { success: false, message: data.message };
        }
    } catch (error) {
        console.error('Login error:', error);
        return { success: false, message: 'An error occurred during login' };
    }
}

// Register Function
async function register(userData) {
    try {
        const response = await fetch(API_URL + 'auth.php?action=register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userData)
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Registration error:', error);
        return { success: false, message: 'An error occurred during registration' };
    }
}

// Logout Function
async function logout() {
    try {
        const response = await fetch(API_URL + 'auth.php?action=logout', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = null;
            updateNav();
            window.location.href = 'index.html';
        }
    } catch (error) {
        console.error('Logout error:', error);
    }
}

// Fetch Products with Filters
async function fetchProducts(filters = {}) {
    try {
        const params = new URLSearchParams();
        
        if (filters.search) params.append('search', filters.search);
        if (filters.category) params.append('category', filters.category);
        if (filters.min_price) params.append('min_price', filters.min_price);
        if (filters.max_price) params.append('max_price', filters.max_price);
        if (filters.sort) params.append('sort', filters.sort);
        if (filters.order) params.append('order', filters.order);
        
        const response = await fetch(API_URL + 'products.php?' + params.toString());
        const data = await response.json();
        
        return data;
    } catch (error) {
        console.error('Fetch products error:', error);
        return { success: false, message: 'Failed to fetch products' };
    }
}

// Fetch Single Product
async function fetchProduct(productId) {
    try {
        const response = await fetch(API_URL + 'products.php?id=' + productId);
        const data = await response.json();
        
        return data;
    } catch (error) {
        console.error('Fetch product error:', error);
        return { success: false, message: 'Failed to fetch product' };
    }
}

// Fetch Categories
async function fetchCategories() {
    try {
        const response = await fetch(API_URL + 'categories.php');
        const data = await response.json();
        
        return data;
    } catch (error) {
        console.error('Fetch categories error:', error);
        return { success: false, message: 'Failed to fetch categories' };
    }
}

// Create Product (Admin)
async function createProduct(productData) {
    try {
        const response = await fetch(API_URL + 'products.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(productData)
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Create product error:', error);
        return { success: false, message: 'Failed to create product' };
    }
}

// Update Product (Admin)
async function updateProduct(productData) {
    try {
        const response = await fetch(API_URL + 'products.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(productData)
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Update product error:', error);
        return { success: false, message: 'Failed to update product' };
    }
}

// Delete Product (Admin)
async function deleteProduct(productId) {
    try {
        const response = await fetch(API_URL + 'products.php?id=' + productId, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Delete product error:', error);
        return { success: false, message: 'Failed to delete product' };
    }
}

// Fetch Orders
async function fetchOrders(orderId = null, all = false) {
    try {
        let url = API_URL + 'orders.php';
        if (orderId) {
            url += '?id=' + orderId;
        } else if (all) {
            url += '?all=1';
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        return data;
    } catch (error) {
        console.error('Fetch orders error:', error);
        return { success: false, message: 'Failed to fetch orders' };
    }
}

// Create Order
async function createOrder(orderData) {
    try {
        const response = await fetch(API_URL + 'orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(orderData)
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Create order error:', error);
        return { success: false, message: 'Failed to create order' };
    }
}

// Update Order Status (Admin)
async function updateOrderStatus(orderId, status, paymentStatus = null) {
    try {
        const updateData = { order_id: orderId, status };
        if (paymentStatus) {
            updateData.payment_status = paymentStatus;
        }
        
        const response = await fetch(API_URL + 'orders.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(updateData)
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Update order error:', error);
        return { success: false, message: 'Failed to update order' };
    }
}

// Fetch Addresses
async function fetchAddresses() {
    try {
        const response = await fetch(API_URL + 'addresses.php');
        const data = await response.json();
        
        return data;
    } catch (error) {
        console.error('Fetch addresses error:', error);
        return { success: false, message: 'Failed to fetch addresses' };
    }
}

// Create Address
async function createAddress(addressData) {
    try {
        const response = await fetch(API_URL + 'addresses.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(addressData)
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Create address error:', error);
        return { success: false, message: 'Failed to create address' };
    }
}

// Submit Review
async function submitReview(reviewData) {
    try {
        const response = await fetch(API_URL + 'reviews.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(reviewData)
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Submit review error:', error);
        return { success: false, message: 'Failed to submit review' };
    }
}

// Shopping Cart (using localStorage)
const Cart = {
    getCart: function() {
        const cart = localStorage.getItem('shopping_cart');
        return cart ? JSON.parse(cart) : [];
    },
    
    addItem: function(product, quantity = 1) {
        const cart = this.getCart();
        const existingItem = cart.find(item => item.product_id === product.product_id);
        
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            cart.push({
                product_id: product.product_id,
                product_name: product.product_name,
                price: product.price,
                quantity: quantity,
                image_url: product.image_url
            });
        }
        
        localStorage.setItem('shopping_cart', JSON.stringify(cart));
        this.updateCartCount();
    },
    
    removeItem: function(productId) {
        let cart = this.getCart();
        cart = cart.filter(item => item.product_id !== productId);
        localStorage.setItem('shopping_cart', JSON.stringify(cart));
        this.updateCartCount();
    },
    
    updateQuantity: function(productId, quantity) {
        const cart = this.getCart();
        const item = cart.find(item => item.product_id === productId);
        
        if (item) {
            item.quantity = quantity;
            if (item.quantity <= 0) {
                this.removeItem(productId);
            } else {
                localStorage.setItem('shopping_cart', JSON.stringify(cart));
            }
        }
        
        this.updateCartCount();
    },
    
    clear: function() {
        localStorage.removeItem('shopping_cart');
        this.updateCartCount();
    },
    
    getTotal: function() {
        const cart = this.getCart();
        return cart.reduce((total, item) => total + (item.price * item.quantity), 0);
    },
    
    updateCartCount: function() {
        const cart = this.getCart();
        const count = cart.reduce((sum, item) => sum + item.quantity, 0);
        const cartCountElement = document.getElementById('cartCount');
        
        if (cartCountElement) {
            cartCountElement.textContent = count;
        }
    }
};

// Show Alert
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

// Format Currency
function formatCurrency(amount) {
    return 'TZS ' + amount.toLocaleString('en-TZ');
}

// Format Date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-TZ', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

// Generate Star Rating HTML
function generateStars(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    let html = '';
    
    for (let i = 0; i < fullStars; i++) {
        html += '★';
    }
    
    if (hasHalfStar) {
        html += '☆';
    }
    
    const remainingStars = 5 - Math.ceil(rating);
    for (let i = 0; i < remainingStars; i++) {
        html += '☆';
    }
    
    return html;
}
