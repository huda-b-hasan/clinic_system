document.getElementById('logout-link').addEventListener('click', function(e) {
    e.preventDefault();

    fetch('/logout', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json', // <--- أضفنا هذا السطر ليطلب لاراكل رد JSON دائماً
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('حدث خطأ في السيرفر');
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            window.location.href = '../auth/login.html';
        }
    })
    .catch(error => console.error('Error:', error));
});