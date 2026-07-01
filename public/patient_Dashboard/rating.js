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

async function loadAllRattings(){
        try {
        const response = await fetch(`/ratings`);
        if (!response.ok) throw new Error('فشل في جلب التقييمات ');
        const rating = await response.json();
        const ratingData=rating.data;
        const ratingContainer=document.getElementById('reviews-grid');
        ratingData.forEach((element)=>{
            let starts=``;
            let counter =element.stars_number;
            while(counter>0){
                starts+=`<svg class="star-icon" viewBox="0 0 24 24" width="24" height="24" fill="#FFD700">
                <path d="M12 .587l3.668 7.431 8.2 1.192-5.934 5.787 1.4 8.168L12 18.896l-7.334 3.857 1.4-8.168L.132 9.21l8.2-1.192z"/>
                </svg>`
               counter--;
            }
            ratingContainer.innerHTML +=`<div class="review-card">
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
        star.addEventListener('mouseover', function() {
            resetStars();
            highlightStars(this.dataset.value);
        });

        star.addEventListener('mouseleave', () => {
            resetStars();
            if (ratingInput.value > 0) {
                highlightStars(ratingInput.value);
            }
        });

        star.addEventListener('click', function() {
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
        fetch('http://127.0.0.1:8000/treatments') 
            .then(response => {
                if (!response.ok) throw new Error("فشل في الاتصال بالسيرفر");
                return response.json();
            })
            .then(data => {
                treatmentSelect.innerHTML = '<option value="" disabled selected>اختر الخدمة...</option>';
                const treatments = Array.isArray(data) ? data : [];
                
                if (treatments.length === 0) {
                    treatmentSelect.innerHTML = '<option value="" disabled>لا يوجد خدمات متاحة حالياً</option>';
                    return;
                }

                treatments.forEach(treatment => {
                    const option = document.createElement("option");
                    option.value = treatment.id;
                    option.textContent = treatment.name;
                    treatmentSelect.appendChild(option);
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
    ratingForm.addEventListener("submit", function (e) {
        e.preventDefault();

        if (!treatmentSelect.value) {
            alert("الرجاء اختيار الخدمة التي قمتِ بتجربتها أولاً.");
            return;
        }

        if (ratingInput.value === "0" || !ratingInput.value) {
            alert("الرجاء اختيار تقييم بالنجوم.");
            return;
        }

        const formData = {
            treatment_id: parseInt(treatmentSelect.value),
            stars_number: parseInt(ratingInput.value),
            comment: treatmentComment.value.trim() || null
            // user_id: 2 // فكي التعليق هنا للتجربة بـ Thunder Client لو لم يجهز الـ Auth بعد
        };

        fetch('http://127.0.0.1:8000/ratings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                // 'Authorization': 'Bearer ' + localStorage.getItem('token') // فكي التعليق عند جاهزية الـ CheckAuth توكن
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(result => {
            if (result.errors) {
                const errorMessages = Object.values(result.errors).flat().join("\n");
                alert("خطأ في البيانات المرسلة:\n" + errorMessages);
                return;
            }

            if (result.message && !result.data) {
                alert(result.message);
                return;
            }

            if (result.data) {
                alert(result.message || "تم إضافة تقييمكِ بنجاح، شكراً لكِ!");
                
                ratingForm.reset();
                resetStars();
                ratingInput.value = "0";
            }
        })
        .catch(error => {
            console.error("خطأ أثناء إرسال التقييم:", error);
            alert("حدث خطأ في الاتصال بالسيرفر، يرجى المحاولة لاحقاً.");
        });
    });
});