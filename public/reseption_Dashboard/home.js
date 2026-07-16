// reseption data 
document.addEventListener('DOMContentLoaded', () => {
    fetchReceptionistProfile();
});

async function fetchReceptionistProfile() {
    try {
        const response = await fetch('/receptionist/profile-data', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        const result = await response.json();
        console.log(result)
        if (result.status === 'success') {
            const user = result.data;

            document.getElementById('receptionist-name').innerText = user.name;

        } else {
            console.error('خطأ:', result.message);
            console.log(result.message);
        }

    } catch (error) {
        console.error('حدث خطأ في جلب بيانات الملف الشخصي:', error);
        alert('فشل الاتصال بالسيرفر. يرجى التحقق من تشغيل سيرفر لارافيل.');
    }
}
// main swetch 

        // دالة التبديل بين التبويبات الأربعة
        function switchTab(tabName) {
            // إخفاء كل المحتويات
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            // إزالة الكلاس الفعال من الأزرار
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            // إظهار المحتوى المطلوب وتفعيل الزر المضغوط
            document.getElementById(tabName + '-appointments').style.display = 'flex';
            event.currentTarget.classList.add('active');
        }