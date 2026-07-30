// ==========================================
// إدارة ومراقبة المودال والتوست لإلغاء المواعيد
// ==========================================
let appointmentIdToCancel = null;

function openCancelModal(id) {
    appointmentIdToCancel = id;
    const modal = document.getElementById('cancelModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeCancelModal() {
    const modal = document.getElementById('cancelModal');
    if (modal) {
        modal.style.display = 'none';
    }
    const reasonInput = document.getElementById('cancellation_reason');
    if (reasonInput) {
        reasonInput.value = "";
    }
    appointmentIdToCancel = null;
}

document.addEventListener("DOMContentLoaded", () => {
    document.addEventListener('click', function(event) {
        if (event.target && event.target.classList.contains('cancel-btn')) {
            const id = event.target.getAttribute('data-id');
            if (id) {
                openCancelModal(id);
            }
        }
    });

    const closeBtn = document.getElementById('closeModalBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeCancelModal);
    }

    const backBtn = document.getElementById('backModalBtn');
    if (backBtn) {
        backBtn.addEventListener('click', closeCancelModal);
    }

    const cancelForm = document.getElementById('cancelAppointmentForm');
    if (cancelForm) {
        cancelForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const idInput = document.getElementById('cancelAppointmentId');
            const targetId = appointmentIdToCancel || (idInput ? idInput.value : null);

            if (!targetId) return;

            const cancellationReasonInput = document.getElementById('cancellation_reason');
            const cancellationReason = cancellationReasonInput ? cancellationReasonInput.value : '';
            const toast = document.getElementById('toast');

            fetch(`/appointments/${targetId}/cancel`, { 
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    cancellation_reason: cancellationReason
                })
            })
            .then(response => response.json())
            .then(data => {
                closeCancelModal();

                if (data.status === 'success' || data.success) {
                    if (toast) {
                        toast.style.background = '#28a745';
                        toast.textContent = `✓ ${data.message || 'تم الإلغاء بنجاح'}`; 
                        toast.classList.add('show');
                    }

                    setTimeout(() => {
                        if (toast) toast.classList.remove('show');
                        if (typeof fetchAppointments === 'function') {
                            fetchAppointments();
                        } else {
                            location.reload(); 
                        }
                    }, 4000);

                } else {
                    if (toast) {
                        toast.style.background = '#dc3545'; 
                        toast.textContent = `⚠️ ${data.message || 'فشل عملية الإلغاء'}`; 
                        toast.classList.add('show');
                    }

                    setTimeout(() => {
                        if (toast) toast.classList.remove('show');
                    }, 3000);
                }
            })
            .catch(err => {
                console.error('Error cancelling appointment:', err);
                closeCancelModal();
            });
        });
    }
});