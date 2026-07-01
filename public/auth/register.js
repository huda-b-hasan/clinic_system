
    const form = document.getElementById('registerForm');
    const roleButtons = document.querySelectorAll('.role-card');
    let selectedRole = 'patient'; 

    roleButtons.forEach(button => {
        button.addEventListener('click', () => {
            roleButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            selectedRole = button.getAttribute('data-role');
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault(); 

        const userData = {
            name: document.getElementById('name').value.trim(),
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value,
            phone: document.getElementById('phone').value.trim(),
            gender:document.querySelector(`input[name="gender"]:checked`)?.value,
            role: selectedRole
        };

        try {

            const response = await fetch('/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json' 
                },
                body: JSON.stringify(userData)
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                alert(result.message);
                // تظبيط حسب الشخص شو ولشو بوجهه ÷*************************************************************
                if(result.user_type=="Patient")
                window.location.href = '/patient_Dashboard/home.html';
            } 
            else {
                if (result.errors) {
                    let errorMessage = 'يرجى التحقق من الأخطاء التالية:\n';
                    for (const field in result.errors) {
                        errorMessage += ` ${result.errors[field].join(', ')}\n`;
                    }
                    alert(errorMessage);
                } else {
                    alert(result.message || 'حدث خطأ أثناء إنشاء الحساب.');
                }
            }

        } catch (error) {
            console.error('Connection Error:', error);
            alert('عذراً، فشل الاتصال بالسيرفر. تأكدي من تشغيل أمر php artisan serve.');
        }
    });