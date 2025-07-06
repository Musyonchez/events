document.addEventListener('DOMContentLoaded', function() {
    // Determine the correct path to the navbar component based on the current page's depth
    const isIndex = window.location.pathname.endsWith('/index.html') || window.location.pathname.endsWith('/');
    const isRootPage = window.location.pathname.split('/').length <= 3; // e.g., /client/index.html
    const pathPrefix = isIndex || isRootPage ? './' : '../';

    const navbarPath = `${pathPrefix}components/navbar.html`;

    fetch(navbarPath)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            const navbarPlaceholder = document.getElementById('navbar-placeholder');
            if (navbarPlaceholder) {
                navbarPlaceholder.innerHTML = data;
            } else {
                console.error("Navbar placeholder not found!");
            }
            
            // After loading the navbar, initialize its functionality
            setTimeout(() => {
                console.log("Before checkAuthState call.");
                initializeNavbar();
                console.log("After checkAuthState call.");
            }, 0);
        })
        .catch(error => {
            console.error('Error loading navbar:', error);
        });
});

function initializeNavbar() {
    // Check authentication state to update UI
    checkAuthState();
}
