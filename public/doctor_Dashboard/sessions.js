document.addEventListener("DOMContentLoaded", function () {
    const container = document.getElementById("sessionsContainer");
    const modal = document.getElementById("cancelModal");
    const confirmCancelBtn = document.getElementById("confirmCancel");
    const closeModalBtn = document.getElementById("closeModal");
    
    let appointmentIdToDelete = null; 
    let cardElementToRemove = null;  

    function showToast(message, type = "success") {
        const toast = document.createElement("div");
        toast.className = `custom-toast ${type}`;
        toast.innerText = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add("show");
        }, 50);

        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 400);
        }, 3000);
    }

    // 1. جلب المواعيد من السيرفر
    fetch("/doctor/appointments")
        .then(response => {
            if (!response.ok) throw new Error("فشل في جلب البيانات");
            return response.json();
        })
        .then(data => {
            if (data.success && data.pending && data.pending.length > 0) {
                container.innerHTML = ""; 

                data.pending.forEach(appointment => {
                    const patientName = appointment.patient ? appointment.patient.name : "مريض غير معروف";
                    const roomName = appointment.room ? appointment.room.name : "غير محددة";
                    
                    let treatmentsText = "لا توجد علاجات محددة";
                    if (appointment.treatments && appointment.treatments.length > 0) {
                        treatmentsText = appointment.treatments.map(t => t.name).join(" ، ");
                    }

                    const card = document.createElement("div");
                    card.className = "session-card";
                    card.innerHTML = `
                        <div class="session-info">
                            <h3>المريض: ${patientName}</h3>
                            <p><strong>التاريخ والوقت:</strong> ${new Date(appointment.appointment_date).toLocaleString('ar-EG')}</p>
                            <p><strong>الغرفة:</strong> ${roomName}</p>
                            <p><strong>العلاجات المطلوبة:</strong> ${treatmentsText}</p>
                        </div>
                        <div class="session-actions">
                            <button class="btn btn-primary start-btn">بدء الجلسة</button>
                            <button class="btn btn-danger cancel-btn" data-id="${appointment.id}">إلغاء الجلسة</button>
                        </div>
                    `;
                    container.appendChild(card);
                });

                attachCancelEvents();
            } else {
                container.innerHTML = '<p style="text-align: center; color: #888;">لا توجد جلسات نشطة أو معلقة اليوم.</p>';
            }
        })
        .catch(error => {
            console.error("Error:", error);
            container.innerHTML = '<p style="text-align: center; color: red;">حدث خطأ أثناء تحميل الجلسات.</p>';
        });

    function attachCancelEvents() {
        const cancelButtons = document.querySelectorAll('.cancel-btn');
        cancelButtons.forEach(button => {
            button.addEventListener('click', function () {
                appointmentIdToDelete = this.getAttribute('data-id');
                cardElementToRemove = this.closest('.session-card');
                modal.classList.add('show');
            });
        });
    }

    closeModalBtn.addEventListener('click', () => {
        modal.classList.remove('show');
        appointmentIdToDelete = null;
        cardElementToRemove = null;
    });

    // 4. تأكيد الإلغاء وإظهار الـ Toast النظيف
    confirmCancelBtn.addEventListener('click', () => {
        if (appointmentIdToDelete && cardElementToRemove) {
            
            confirmCancelBtn.disabled = true;
            confirmCancelBtn.innerText = "جاري الإلغاء...";

            fetch(`/appointments/${appointmentIdToDelete}/cancel`, {
                method: 'PUT', 
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    cardElementToRemove.style.opacity = '0';
                    cardElementToRemove.style.transition = 'opacity 0.3s ease';
                    
                    setTimeout(() => {
                        cardElementToRemove.remove();
                        if (container.children.length === 0) {
                            container.innerHTML = '<p style="text-align: center; color: #888;">لا توجد جلسات نشطة أو معلقة اليوم.</p>';
                        }
                    }, 300);

                    showToast("تم إلغاء الجلسة بنجاح", "success");

                } else {
                    showToast(result.message || "فشل إلغاء الجلسة", "error");
                }
            })
            .catch(error => {
                console.error("Error updating database:", error);
                showToast("حدث خطأ في الاتصال بالسيرفر", "error");
            })
            .finally(() => {
                confirmCancelBtn.disabled = false;
                confirmCancelBtn.innerText = "نعم، قم بالإلغاء";
                modal.classList.remove('show');
                appointmentIdToDelete = null;
                cardElementToRemove = null;
            });
        }
    });

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('show');
            appointmentIdToDelete = null;
            cardElementToRemove = null;
        }
    });
});