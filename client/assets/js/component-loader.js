document.addEventListener('DOMContentLoaded', function() {
    // Determine the correct path to the navbar component based on the current page's depth
    const navbarPath = '/components/navbar.html';

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
                setupNavbarEventListeners();
                checkAuthState();
            }, 0);
        })
        .catch(error => {
            console.error('Error loading navbar:', error);
        });
});
