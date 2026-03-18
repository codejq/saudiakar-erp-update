/**
 * Edit Tenant Scripts - Modernized
 * @version 2.0
 * @updated 2025-11-18
 */

/**
 * Bootstrap 5.3 Toast Notification System
 */
function showBootstrapNotification(message, type = "success", duration = 3000) {
    const toastId = "toast-" + Date.now();

    const iconMap = {
        success: "bi-check-circle-fill text-success",
        error: "bi-x-circle-fill text-danger",
        warning: "bi-exclamation-triangle-fill text-warning",
        loading: "bi-hourglass-split text-info",
    };

    const bgMap = {
        success: "bg-success-subtle",
        error: "bg-danger-subtle",
        warning: "bg-warning-subtle",
        loading: "bg-info-subtle",
    };

    const icon = iconMap[type] || iconMap["success"];
    const bgClass = bgMap[type] || bgMap["success"];

    const toastHtml = `
        <div id="${toastId}" class="toast ${bgClass} border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-body d-flex align-items-center gap-3 p-3">
                <i class="bi ${icon} fs-4"></i>
                <div class="flex-grow-1" dir="rtl">${message}</div>
                ${
                    type !== "loading"
                        ? '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>'
                        : ""
                }
            </div>
        </div>
    `;

    const container = document.getElementById("toast-container");
    if (!container) {
        console.error("Toast container not found");
        return null;
    }

    container.insertAdjacentHTML("beforeend", toastHtml);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: type !== "loading",
        delay: duration,
    });

    toast.show();

    toastElement.addEventListener("hidden.bs.toast", function () {
        toastElement.remove();
    });

    return toast;
}

/**
 * Submit tenant form via AJAX
 */
function submitTenantForm(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    formData.append("ajax_submit", "1");

    const loadingToast = showBootstrapNotification(
        "جاري حفظ البيانات...",
        "loading",
        0
    );

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML =
        '<i class="bi bi-hourglass-split me-1"></i>جاري الحفظ...';

    fetch(form.action, {
        method: "POST",
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (loadingToast) {
                loadingToast.hide();
            }

            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                showBootstrapNotification(data.message, "success");
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showBootstrapNotification(
                    data.message || "حدث خطأ أثناء الحفظ",
                    "error"
                );
            }
        })
        .catch((error) => {
            console.error("Error:", error);

            if (loadingToast) {
                loadingToast.hide();
            }

            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            showBootstrapNotification("حدث خطأ في الاتصال بالخادم", "error");
        });
}

/**
 * Confirm tenant deletion
 */

function confirmTenantDeletion(mostagerid) {
    confirm("سيتم حذف بيانات المستأجر نهائياً من النظام. هل أنت متأكد؟", "تأكيد الحذف", () => doDelete(mostagerid));
}

async function doDelete(mostagerid) {
    let loadingToast = null;
    try {
        loadingToast = showBootstrapNotification(
            "جاري حذف المستأجر...",
            "loading",
            0
        );
    } catch (e) {
        console.warn("Toast error:", e);
    }

    const formData = new FormData();
    formData.append("deletemostagerid", mostagerid);
    formData.append("ajax_submit", "1");

    fetch("editmostager.hnt", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (loadingToast) {
                loadingToast.hide();
            }

            if (data.success) {
                showBootstrapNotification(data.message, "success");
                setTimeout(() => {
                    window.location.href = "egarstatus.hnt";
                }, 1500);
            } else {
                showBootstrapNotification(data.message, "error", 5000);
            }
        })
        .catch((error) => {
            console.error("Error:", error);
            if (loadingToast) {
                loadingToast.hide();
            }
            showBootstrapNotification(
                "حدث خطأ في الاتصال بالخادم",
                "error"
            );
        });
}

/**
 * Confirm file deletion
 */
function confirmFileDeletion(filename, mostagerid) {
    confirm("هل ترغب بالفعل في حذف الملف؟..", "تأكيد الحذف", () => doDeleteFile(filename, mostagerid));
}

async function doDeleteFile(filename, mostagerid) {
    let loadingToast = null;
    try {
        loadingToast = showBootstrapNotification(
            "جاري حذف الملف...",
            "loading",
            0
        );
    } catch (e) {
        console.warn("Toast error:", e);
    }

    const formData = new FormData();
    formData.append("deletefile", filename);
    formData.append("mostagerid", mostagerid);
    formData.append("ajax_submit", "1");

    fetch("editmostager.hnt", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.json())
        .then((data) => {
            if (loadingToast) {
                loadingToast.hide();
            }

            if (data.success) {
                showBootstrapNotification(data.message, "success");
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showBootstrapNotification(
                    data.message || "حدث خطأ أثناء حذف الملف",
                    "error"
                );
            }
        })
        .catch((error) => {
            console.error("Error:", error);
            if (loadingToast) {
                loadingToast.hide();
            }
            showBootstrapNotification(
                "حدث خطأ في الاتصال بالخادم",
                "error"
            );
        });
}

/**
 * Preview image
 */
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById("tenantImagePreview").src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Handle file upload
 */
function handleFileUpload(event) {
    const fileInput = event.target;
    if (fileInput.files && fileInput.files[0]) {
        showBootstrapNotification("جاري تحميل الملف...", "loading", 0);
        document.getElementById("fileUploadForm").submit();
    }
}
