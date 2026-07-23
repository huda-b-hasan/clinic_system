document.addEventListener("DOMContentLoaded", function () {
    const profileForm = document.getElementById("profileForm");
    const userInitials = document.getElementById("userInitials");
    const patientHeaderName = document.getElementById("patientHeaderName");

    // 1. جلب البيانات عند تحميل الصفحة
    fetch('/profile')
        .then(response => response.json())
        .then(data => {
            document.getElementById("name").value = data.profile.name || "";
            document.getElementById("phone").value = data.profile.phone || "";
            document.getElementById("email").value = data.email || "";
            document.getElementById("birthdate").value = data.profile.birthdate || "";

            const genderText = data.profile.gender === 'female' ? 'أنثى' : (data.profile.gender === 'male' ? 'ذكر' : 'غير محدد');
            document.getElementById("genderStatic").value = genderText;
            document.getElementById("userRoleBadge").innerText = data.role;

            if (document.getElementById("address")) document.getElementById("address").value = data.profile.address || "";
            if (document.getElementById("medical_notes")) document.getElementById("medical_notes").value = data.profile.medical_notes || "";

            if (data.profile.name) {
                patientHeaderName.innerText = data.profile.name;
                userInitials.innerText = data.profile.name.charAt(0).toUpperCase();
            }
        });

    profileForm.addEventListener("submit", function (e) {
        e.preventDefault();

        const formData = {
            _method: 'PUT',
            name: document.getElementById("name").value,
            phone: document.getElementById("phone").value,
            birthdate: document.getElementById("birthdate").value || null,
            address: document.getElementById("address") ? document.getElementById("address").value : null,
            medical_notes: document.getElementById("medical_notes") ? document.getElementById("medical_notes").value : null
        };

        fetch('/profile/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(formData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log(data.message);
                    patientHeaderName.innerText = formData.name;
                    userInitials.innerText = formData.name.charAt(0).toUpperCase();
                    
                } else {
                    console.log("حدث خطأ أثناء حفظ البيانات.");
                }
                if ( data.status === 'success') {
                    // 
                    const toast = document.getElementById('toast');
                    toast.textContent = data.message;
                    toast.classList.add('show');

                    setTimeout(() => {
                        toast.classList.remove('show');
                    }, 3000);

                    fetchProfileData();
                } else {
                    console.log(result.message || 'حدث خطأ ما أثناء التحديث.');
                }
            })
            .catch(error => console.error("Error updating profile:", error));
    });
});