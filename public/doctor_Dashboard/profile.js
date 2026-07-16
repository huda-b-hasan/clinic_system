// جلب عناصر واجهة المستخدم
const profileForm = document.getElementById('profileForm');
const nameInput = document.getElementById('name');
const phoneInput = document.getElementById('phone');
const emailInput = document.getElementById('email');
const userInitials = document.getElementById('userInitials');
const patientHeaderName = document.getElementById('patientHeaderName');
const userRoleBadge = document.getElementById('userRoleBadge');

async function fetchProfileData() {
    try {
        const response = await fetch('/profile', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('فشل في جلب بيانات الملف الشخصي.');
        }

        const data = await response.json();

        nameInput.value = data.profile.name || '';
        phoneInput.value = data.profile.phone || '';
        emailInput.value = data.email || '';
        
        patientHeaderName.textContent = data.profile.name || 'مستخدِم لافندر';
        userRoleBadge.textContent = data.role === 'Patient' ? 'مريض/ة' : data.role;

        if (data.profile.name) {
            userInitials.textContent = data.profile.name.charAt(0).toUpperCase();
        }

    } catch (error) {
        console.error('Error:', error);
        alert('حدث خطأ أثناء تحميل بيانات الملف الشخصي.');
    }
}

document.addEventListener('DOMContentLoaded', fetchProfileData);

profileForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = {
        name: nameInput.value,
        phone: phoneInput.value,
        birthdate: null,
        address: null,
        medical_notes: null
    };

    try {
        const response = await fetch('/profile/update', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            console.log(result.message);
            fetchProfileData(); 
        } else {
            console.log(result.message || 'حدث خطأ ما أثناء التحديث.');
        }
        if (response.ok && result.status === 'success') {
            // 
            const toast = document.getElementById('toast');
            toast.textContent = result.message; 
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);

            fetchProfileData(); 
        } else {
            console.log(result.message || 'حدث خطأ ما أثناء التحديث.');
        }

    } catch (error) {
        console.error('Error:', error);
        console.log('فشل الاتصال بالسيرفر، يرجى المحاولة لاحقاً.');
    }
});