document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault(); 

    const emailInput = document.getElementById('email').value.trim();
    const passwordInput = document.getElementById('password').value;
    const errorMessage = document.getElementById('errorMessage');
    const submitBtn = document.getElementById('submitBtn');
    const role = document.getElementById('role').value;
    
    errorMessage.textContent = ''; 
    submitBtn.textContent = 'جاري التحقق...';
    submitBtn.disabled = true;

    const loginData = {
        email: emailInput,
        password: passwordInput,
        role:role
    };

    try {
        const response = await fetch('/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json' 
            },
            body: JSON.stringify(loginData),
            credentials:'include'
        });

        const data = await response.json(); 

        if (response.ok && data.status === 'success') {
            console.log(response)
            
            localStorage.setItem('user_name', data.user_name);
            localStorage.setItem('user_type', data.user_type);

            if (data.user_type === 'Manager') {
                window.location.href = '../manager/dashboard.html'; 
            } else if (data.user_type === 'Doctor') {
                window.location.href = '../doctor_Dashboard/home.html'; 
            } else if(data.user_type ==="Patient") {
                window.location.href = '/patient_Dashboard/home.html';
            }

        } else {

            errorMessage.textContent = data.message || 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
        }

    } catch (error) {
        console.error('Fetch Error:', error);
        errorMessage.textContent = 'خطأ في الاتصال بالسيرفر. تأكدي من تشغيل php artisan serve';
    } finally {
        submitBtn.textContent = 'دخول';
        submitBtn.disabled = false;
    }
});