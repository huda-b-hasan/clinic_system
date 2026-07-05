// document.addEventListener('DOMContentLoaded', () => {
//     const stars = document.querySelectorAll('#starRatingContainer .star-input');
//     const ratingInput = document.getElementById('selectedRating');

//     stars.forEach(star => {
//         star.addEventListener('mouseover', function() {
//             resetStars();
//             highlightStars(this.dataset.value);
//         });

//         star.addEventListener('mouseleave', () => {
//             resetStars();
//             if (ratingInput.value > 0) {
//                 highlightStars(ratingInput.value);
//             }
//         });

//         star.addEventListener('click', function() {
//             ratingInput.value = this.dataset.value;
//             highlightStars(this.dataset.value);
//         });
//     });

//     function highlightStars(value) {
//         stars.forEach(star => {
//             if (parseInt(star.dataset.value) <= parseInt(value)) {
//                 star.classList.add('active');
//             }
//         });
//     }

//     function resetStars() {
//         stars.forEach(star => {
//             star.classList.remove('active');
//         });
//     }
// });
// get all rattings

async function loadAllRattings() {
    try {
        const response = await fetch(`/ratings`);
        if (!response.ok) throw new Error('فشل في جلب التقييمات ');
        const rating = await response.json();
        const ratingData = rating.data;
        const ratingContainer = document.getElementById('reviews-grid');
        ratingData.forEach((element) => {
            let starts = ``;
            let counter = element.stars_number;
            while (counter > 0) {
                starts += `<svg class="star-icon" viewBox="0 0 24 24" width="24" height="24" fill="#FFD700">
                <path d="M12 .587l3.668 7.431 8.2 1.192-5.934 5.787 1.4 8.168L12 18.896l-7.334 3.857 1.4-8.168L.132 9.21l8.2-1.192z"/>
                </svg>`
                counter--;
            }
            ratingContainer.innerHTML += `<div class="review-card">
                <div class="review-header">
                    <div class="client-info">
                        <span class="client-avatar">${element.user.name[0]}</span>
                        <div>
                            <h4 class="client-name">${element.user.name}</h4>
                            <span class="treatment-name">${element.treatment.name}</span>
                        </div>

                    </div>
                    <div class="stars">
                    ${starts}
                    </div>
                </div>
                <p class="review-text">
                    ${element.comment}
                </p>
            </div>
            `;
        })




    } catch (error) {
        console.error('حدث خطأ أثناء تحميل التقييمات :', error);
    }

}
document.addEventListener('DOMContentLoaded', loadAllRattings);
// store new ratting
document.addEventListener('DOMContentLoaded', () => {
    const stars = document.querySelectorAll('#starRatingContainer .star-input');
    const ratingInput = document.getElementById('selectedRating');

    const treatmentSelect = document.getElementById("treatmentSelect");
    const ratingForm = document.getElementById("ratingForm");
    const treatmentComment = document.getElementById("treatmentComment");

    // ==========================================
    // 1. منطق تفاعل وتأثيرات النجوم (كودكِ المميّز)
    // ==========================================
    stars.forEach(star => {
        star.addEventListener('mouseover', function () {
            resetStars();
            highlightStars(this.dataset.value);
        });

        star.addEventListener('mouseleave', () => {
            resetStars();
            if (ratingInput.value > 0) {
                highlightStars(ratingInput.value);
            }
        });

        star.addEventListener('click', function () {
            ratingInput.value = this.dataset.value;
            highlightStars(this.dataset.value);
        });
    });

    function highlightStars(value) {
        stars.forEach(star => {
            if (parseInt(star.dataset.value) <= parseInt(value)) {
                star.classList.add('active');
            }
        });
    }

    function resetStars() {
        stars.forEach(star => {
            star.classList.remove('active');
        });
    }

    // ==========================================
    // treatment from back into select
    // ==========================================
    function loadTreatments() {
        fetch('/patient/recentTreatments')
            .then(response => {
                if (!response.ok) throw new Error("فشل في الاتصال بالسيرفر");
                return response.json();
            })
            .then(data => {
                treatmentSelect.innerHTML = '<option value="" disabled selected>اختر الخدمة...</option>';
                console.log(data)
                const pastSessions = data.data
                
                pastSessions.forEach(session => {
                        console.log(session.treatment_name)
                        treatmentSelect.innerHTML+=`<option value=${session.treatment_id}>${session.treatment_name}</option>`
                });


            })
            .catch(error => {
                console.error("خطأ في جلب الخدمات:", error);
                treatmentSelect.innerHTML = '<option value="" disabled>فشل تحميل الخدمات</option>';
            });
    }

    loadTreatments();

    // ==========================================
    // submin store new ratting
    // ==========================================
// دالة مساعدة لإنشاء وإظهار كرت التنبيه (div) بدلاً من الـ alert
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    // إنشاء الـ div برمجياً
    const toast = document.createElement('div');
    toast.className = `custom-toast ${type}`;
    
    // تحويل الـ \n لكسر أسطر HTML حقيقي ليظهر النص منسقاً بالمنتصف
    toast.innerHTML = message.replace(/\n/g, "<br>");

    // إضافة التنبيه داخل الحاوية المتمركزة
    container.appendChild(toast);

    // حذفه برمجياً بعد انتهاء وقت الحركات تماماً
    setTimeout(() => {
        toast.remove();
    }, 4000);
}

    ratingForm.addEventListener("submit", function (e) {
        e.preventDefault();

        if (!treatmentSelect.value) {
            showToast("الرجاء اختيار الخدمة التي قمتِ بتجربتها أولاً.", "error");
            return;
        }

        if (ratingInput.value === "0" || !ratingInput.value) {
            showToast("الرجاء اختيار تقييم بالنجوم.", "error");
            return;
        }

        // 1. بناء الكائن الأساسي
        const formData = {
            treatment_id: parseInt(treatmentSelect.value),
            stars_number: parseInt(ratingInput.value)
        };

        // 2. التحقق من التعليق
        const commentValue = treatmentComment.value.trim();
        if (commentValue) {
            formData.comment = commentValue;
        }

        fetch('/ratings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                // 'Authorization': 'Bearer ' + localStorage.getItem('token')
            },
            body: JSON.stringify(formData)
        })
            .then(response => response.json())
            .then(result => {
                if (result.errors) {
                    const errorMessages = Object.values(result.errors).flat().join("\n");
                    showToast("خطأ في البيانات المرسلة:\n" + errorMessages, "error");
                    return;
                }

                if (result.message && !result.data) {
                    showToast(result.message, "error");
                    return;
                }

                if (result.data) {
                    showToast(result.message || "تم إضافة تقييمكِ بنجاح، شكراً لكِ!", "success");

                    ratingForm.reset();
                    resetStars();
                    ratingInput.value = "0";
                }
            })
            .catch(error => {
                console.error("خطأ أثناء إرسال التقييم:", error);
                showToast("حدث خطأ في الاتصال بالسيرفر، يرجى المحاولة لاحقاً.", "error");
            });
    });
});