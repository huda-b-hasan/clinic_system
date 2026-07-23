document.addEventListener("DOMContentLoaded", function () {
    
    // 1. قراءة appointment_id من رابط الصفحة (URL)
    const urlParams = new URLSearchParams(window.location.search);
    const appointmentId = urlParams.get('appointment_id');

    if (!appointmentId) {
        showToast("لم يتم تحديد رقم الموعد!", "error");
        console.error("Missing appointment_id in URL search params.");
        setTimeout(() => {
            window.location.href = "sessions.html";
        }, 2000);
        return;
    }

    // عناصر الصفحة
    const form = document.getElementById("clinicSessionForm");
    const materialsContainer = document.getElementById("materialsContainer");
    const showAddMaterialBtn = document.getElementById("showAddMaterialBtn");
    const addMaterialDropdownRow = document.getElementById("addMaterialDropdownRow");
    const extraMaterialSelect = document.getElementById("extraMaterialSelect");
    const confirmAddMaterialBtn = document.getElementById("confirmAddMaterialBtn");

    if (materialsContainer) {
        materialsContainer.innerHTML = "";
    }

    // 2. طلب البيانات من الـ Backend عند تحميل الصفحة
    loadSessionPageData();

    function loadSessionPageData() {
        Promise.all([
            fetch(`/doctor/session-details/${appointmentId}`).then(res => res.json()),
            fetch(`/materials/available`).then(res => res.json())
        ])
        .then(([sessionRes, materialsRes]) => {
            console.log("Session details response:", sessionRes);
            console.log("Available materials response:", materialsRes);

            if (sessionRes.success) {
                populateSessionInfo(sessionRes.data);
            } else {
                showToast(sessionRes.message || "فشل تحميل بيانات الجلسة", "error");
                console.warn("Session Details Fetch Warning:", sessionRes);
            }

            if (materialsRes.success) {
                populateExtraMaterialsDropdown(materialsRes.data);
            } else {
                showToast("فشل جلب المواد المتاحة بالمخزن", "warning");
                console.warn("Materials Fetch Warning:", materialsRes);
            }
        })
        .catch(err => {
            console.error("Error loading session data:", err);
            showToast("حدث خطأ أثناء جلب بيانات الجلسة من السيرفر", "error");
        });
    }

    // 3. تعبئة بيانات المريض والعلاج
    function populateSessionInfo(data) {
        const patientNameEl = document.getElementById("patientName");
        const patientSubtextEl = document.querySelector(".patient-subtext");
        
        if (patientNameEl) patientNameEl.textContent = data.patient.name;
        if (patientSubtextEl) {
            const ageText = data.patient.age ? `${data.patient.age} سنة` : 'غير محدد';
            patientSubtextEl.textContent = `العمر: ${ageText} • هاتف: ${data.patient.phone}`;
        }

        const treatmentNameEl = document.getElementById("treatmentName");
        const deviceNameEl = document.getElementById("deviceName");
        const roomNameEl = document.getElementById("roomName");
        const bookprice = document.getElementById("book-price");

        if (data.treatments && data.treatments.length > 0) {
            const treatment = data.treatments[0];
            if (treatmentNameEl) treatmentNameEl.textContent = treatment.name;
            
            const displayPrice = data.total_booked_price ?? treatment.booked_price ?? 0;
            if (bookprice) bookprice.innerHTML = `${displayPrice} ل.س`;

            const devices = treatment.devices && treatment.devices.length > 0 ? treatment.devices.join(" ، ") : "لا يوجد جهاز محدد";
            if (deviceNameEl) deviceNameEl.textContent = devices;

            if (treatment.default_materials && treatment.default_materials.length > 0) {
                treatment.default_materials.forEach(mat => {
                    addMaterialCardToDOM(mat.id, `${mat.name} (أساسي)`, mat.unit_price, 1);
                });
            }
        }

        if (roomNameEl) roomNameEl.textContent = data.room ? data.room.name : 'غير محددة';
    }

    // 4. تعبئة القائمة المنسدلة للمواد الإضافية
    function populateExtraMaterialsDropdown(materials) {
        if (!extraMaterialSelect) return;
        extraMaterialSelect.innerHTML = '<option value="" disabled selected>اختر المادة من المخزن...</option>';

        materials.forEach(mat => {
            const option = document.createElement("option");
            option.value = mat.id;
            option.setAttribute("data-price", mat.unit_price);
            option.textContent = `${mat.name} — (${mat.unit_price} ل.س)`;
            extraMaterialSelect.appendChild(option);
        });
    }

    // 5. إظهار/إخفاء قائمة المواد الإضافية
    if (showAddMaterialBtn) {
        showAddMaterialBtn.addEventListener("click", () => {
            const isHidden = addMaterialDropdownRow.style.display === "none";
            addMaterialDropdownRow.style.display = isHidden ? "flex" : "none";
        });
    }

    // 6. إضافة مادة إضافية عند الضغط على تأكيد الإضافة
    if (confirmAddMaterialBtn) {
        confirmAddMaterialBtn.addEventListener("click", () => {
            const selectedOpt = extraMaterialSelect.options[extraMaterialSelect.selectedIndex];
            if (!extraMaterialSelect.value) {
                showToast("الرجاء اختيار مادة من المنسدلة أولاً", "warning");
                return;
            }

            const matId = extraMaterialSelect.value;
            const matName = selectedOpt.text.split('—')[0].trim();
            const matPrice = selectedOpt.getAttribute("data-price");

            if (document.querySelector(`input[name="materials[${matId}]"]`)) {
                showToast("هذه المادة مضافة بالفعل في القائمة!", "warning");
                return;
            }

            addMaterialCardToDOM(matId, `${matName} (إضافي)`, matPrice, 1);
            showToast(`تمت إضافة ${matName} للجلسة`, "success");

            addMaterialDropdownRow.style.display = "none";
            extraMaterialSelect.selectedIndex = 0;
        });
    }

    // دالة مساعدة لإضافة كارت المادة
    function addMaterialCardToDOM(id, name, price, defaultQty = 0) {
        const matCard = document.createElement("div");
        matCard.className = "material-card";
        matCard.innerHTML = `
            <div class="mat-details">
                <span class="mat-name">${name}</span>
                <span class="mat-unit-price">السعر للوحدة: <strong class="price-val">${price}</strong> ل.س</span>
            </div>
            <div class="qty-counter">
                <button type="button" class="qty-btn minus">-</button>
                <input type="number" name="materials[${id}]" value="${defaultQty}" min="0" readonly class="qty-input" data-price="${price}">
                <button type="button" class="qty-btn plus">+</button>
            </div>
        `;
        materialsContainer.appendChild(matCard);


    }

    // 7. التحكم بأزرار الزيادة والنقصان (+) و (-)
    if (materialsContainer) {
        materialsContainer.addEventListener("click", (e) => {
            if (e.target.classList.contains("plus")) {
                const input = e.target.previousElementSibling;
                input.value = parseInt(input.value || 0) + 1;
            } else if (e.target.classList.contains("minus")) {
                const input = e.target.nextElementSibling;
                const currentVal = parseInt(input.value || 0);
                if (currentVal > 0) {
                    input.value = currentVal - 1;
                }
            }
        });
    }

    // 8. إرسال النموذج وحفظ الجلسة وإصدار الفاتورة
    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            const materialsData = {};
            const qtyInputs = document.querySelectorAll('.qty-input');
            
            qtyInputs.forEach(input => {
                const nameAttr = input.getAttribute('name');
                const matId = nameAttr.match(/\d+/)[0];
                const qty = parseInt(input.value || 0);
                if (qty > 0) {
                    materialsData[matId] = qty;
                }
            });

            const doctorNotes = document.getElementById("doctorNotes") ? document.getElementById("doctorNotes").value : "";

            const payload = {
                doctor_notes: doctorNotes,
                materials: materialsData
            };

            console.log("Submitting session payload:", payload);

            fetch(`/doctor/session-complete/${appointmentId}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                console.log("Session complete response:", data);
                if (data.success) {
                    showToast("✓ تم إنهاء الجلسة وإصدار الفاتورة بنجاح!", "success");
                    setTimeout(() => {
                        window.location.href = "sessions.html";
                    }, 1500);
                } else {
                    showToast(data.message || "تعذر إنهاء الجلسة", "error");
                    console.error("Backend Error:", data);
                }
            })
            .catch(err => {
                console.error("Error submitting session:", err);
                showToast("حدث خطأ أثناء الاتصال بالسيرفر لتأكيد الجلسة", "error");
            });
        });
    }

    // ================= HELPER: CUSTOM TOAST FUNCTION =================
    function showToast(message, type = "info") {
        const toast = document.getElementById("toast");
        if (!toast) {
            console.log(`[Toast Fallback - ${type.toUpperCase()}]: ${message}`);
            return;
        }

        toast.textContent = message;
        toast.className = `toast ${type} show`;

        setTimeout(() => {
            toast.className = toast.className.replace("show", "").trim();
        }, 3000);
    }

});