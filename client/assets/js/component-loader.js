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
                // Dispatch a custom event after the navbar is loaded
                document.dispatchEvent(new Event('navbarLoaded'));
            } else {
                console.error("Navbar placeholder not found!");
            }
        })
        .catch(error => {
            console.error('Error loading navbar:', error);
        });
});
