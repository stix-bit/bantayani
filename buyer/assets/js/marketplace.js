// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    // Add filter and search section to the page
    addFilterSection();
    
    // Add scroll to top button
    addScrollTopButton();
    
    // Initialize features
    initializeSearch();
    initializeSort();
    initializeCardAnimations();
    initializeScrollEffects();
    
});

// Add filter section HTML
function addFilterSection() {
    const marketplace = document.querySelector('.marketplace');
    const filterHTML = `
        <div class="filter-section">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search crops or farms...">
                <span class="search-icon">🔍</span>
            </div>
            <select id="sortSelect" class="sort-select">
                <option value="default">Sort by...</option>
                <option value="name-asc">Name (A-Z)</option>
                <option value="name-desc">Name (Z-A)</option>
                <option value="price-asc">Price (Low to High)</option>
                <option value="price-desc">Price (High to Low)</option>
                <option value="quantity-asc">Quantity (Low to High)</option>
                <option value="quantity-desc">Quantity (High to Low)</option>
            </select>
        </div>
    `;
    marketplace.insertAdjacentHTML('beforebegin', filterHTML);
}

// Add scroll to top button
function addScrollTopButton() {
    const scrollBtn = document.createElement('button');
    scrollBtn.className = 'scroll-top';
    scrollBtn.innerHTML = '↑';
    scrollBtn.setAttribute('aria-label', 'Scroll to top');
    document.body.appendChild(scrollBtn);
    
    scrollBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// Initialize search functionality
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const cards = document.querySelectorAll('.card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const cropName = card.querySelector('b').textContent.toLowerCase();
            const farmName = card.querySelector('p').textContent.toLowerCase();
            
            if (cropName.includes(searchTerm) || farmName.includes(searchTerm)) {
                card.style.display = 'block';
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 10);
                visibleCount++;
            } else {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.display = 'none';
                }, 300);
            }
        });
        
        // Show/hide no results message
        showNoResultsMessage(visibleCount);
    });
}

// Initialize sort functionality
function initializeSort() {
    const sortSelect = document.getElementById('sortSelect');
    
    if (!sortSelect) return;
    
    sortSelect.addEventListener('change', function(e) {
        const sortValue = e.target.value;
        const marketplace = document.querySelector('.marketplace');
        const cards = Array.from(document.querySelectorAll('.card'));
        
        if (sortValue === 'default') return;
        
        cards.sort((a, b) => {
            let aValue, bValue;
            
            switch(sortValue) {
                case 'name-asc':
                case 'name-desc':
                    aValue = a.querySelector('b').textContent.toLowerCase();
                    bValue = b.querySelector('b').textContent.toLowerCase();
                    return sortValue === 'name-asc' 
                        ? aValue.localeCompare(bValue)
                        : bValue.localeCompare(aValue);
                    
                case 'price-asc':
                case 'price-desc':
                    aValue = parseFloat(a.querySelectorAll('p')[1].textContent.replace(/[^0-9.]/g, ''));
                    bValue = parseFloat(b.querySelectorAll('p')[1].textContent.replace(/[^0-9.]/g, ''));
                    return sortValue === 'price-asc' ? aValue - bValue : bValue - aValue;
                    
                case 'quantity-asc':
                case 'quantity-desc':
                    aValue = parseFloat(a.querySelectorAll('p')[2].textContent.replace(/[^0-9.]/g, ''));
                    bValue = parseFloat(b.querySelectorAll('p')[2].textContent.replace(/[^0-9.]/g, ''));
                    return sortValue === 'quantity-asc' ? aValue - bValue : bValue - aValue;
            }
        });
        
        // Reorder cards with animation
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.8)';
        });
        
        setTimeout(() => {
            cards.forEach(card => marketplace.appendChild(card));
            
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1)';
                }, index * 50);
            });
        }, 300);
    });
}

// Initialize card animations on hover and click
function initializeCardAnimations() {
    const cards = document.querySelectorAll('.card');
    
    cards.forEach(card => {
        // Add ripple effect on click
        card.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = card.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            const existingRipple = card.querySelector('.ripple');
            if (existingRipple) {
                existingRipple.remove();
            }
            
            card.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
        
        // Add tilt effect on mouse move
        card.addEventListener('mousemove', function(e) {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 10;
            const rotateY = (centerX - x) / 10;
            
            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.02)`;
        });
        
        card.addEventListener('mouseleave', function() {
            card.style.transform = '';
        });
    });
}

// Initialize scroll effects
function initializeScrollEffects() {
    const scrollBtn = document.querySelector('.scroll-top');
    
    window.addEventListener('scroll', function() {
        // Show/hide scroll to top button
        if (window.pageYOffset > 300) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });
}

// Show no results message
function showNoResultsMessage(visibleCount) {
    let noResultsMsg = document.querySelector('.no-results');
    
    if (visibleCount === 0) {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.className = 'no-results';
            noResultsMsg.innerHTML = '😔 No products found. Try a different search term.';
            document.querySelector('.marketplace').appendChild(noResultsMsg);
        }
    } else {
        if (noResultsMsg) {
            noResultsMsg.remove();
        }
    }
}

// Add CSS for ripple effect
const style = document.createElement('style');
style.textContent = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(31, 138, 112, 0.3);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Add loading animation when page loads
window.addEventListener('load', function() {
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
