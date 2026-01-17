<?php
// Frontend for adding new offers
$sc_title = "إضافة عرض عقاري جديد";
$sc_id = "72";
$canscroll = 1;

include_once("../../nocash.hnt");
include("../../header.hnt");


// Include prefill data if legacy system ID is provided
if (isset($_GET['ardid']) || isset($_GET['emaraid']) || isset($_GET['vilaid'])) {
    include("prefill_data.php");
}

// Get default map center from database (same as dep_add_edit.hnt)
$mainlat = 21.583061886336214;
$mainlng = 39.20806601643562;
$sql = "SELECT center_x, center_y FROM maps ORDER BY id DESC LIMIT 1";
$row = mysql_fetch_array(mysql_query($sql, $link));
if ($row && $row['center_x'] != 0) {
    $mainlat = $row['center_x'];
    $mainlng = $row['center_y'];
}
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin=""/>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<!-- Map Fullscreen Styles -->
<style>
    #mapCard.fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100% !important;
        height: 100% !important;
        z-index: 9999;
        margin: 0 !important;
        border-radius: 0 !important;
        max-width: 100% !important;
    }

    #mapCard.fullscreen .card-body {
        height: calc(100% - 56px);
        padding: 0 !important;
    }

    #mapCard.fullscreen #map {
        height: 100% !important;
        border-radius: 0 !important;
    }

    #mapCard.fullscreen .card-header {
        border-radius: 0 !important;
    }

    #mapCard.fullscreen small {
        position: absolute;
        bottom: 10px;
        left: 10px;
        background: white;
        padding: 8px 12px;
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        z-index: 1000;
    }

    /* Image Preview Styles */
    #imagesPreview .card {
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
    }

    #imagesPreview .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    #imagesPreview .card img {
        transition: transform 0.3s;
    }

    #imagesPreview .card:hover img {
        transform: scale(1.05);
    }

    #imagesPreview .btn-sm {
        opacity: 0.9;
    }

    #imagesPreview .btn-sm:hover {
        opacity: 1;
    }
</style>

<?php
require_once("ApiKey.php");
// Read API configuration
$API_BASE_URL = 'https://api.saudiakar.net';
$api_key = getApiKey();
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="bi bi-plus-circle me-2"></i>
                    إضافة عرض عقاري جديد
                </h2>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                    <i class="bi bi-arrow-right me-2"></i>
                    رجوع للقائمة
                </button>
            </div>
        </div>
    </div>

    <?php if (isset($prefill_data['source_type']) && $prefill_data['source_id']): ?>
    <!-- Pre-fill Info Banner -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                <div>
                    <strong>تم تحميل البيانات من النظام </strong>
                    <p class="mb-0">
                        تم تعبئة الحقول تلقائياً من سجل
                        <?php
                        $source_labels = [
                            'land' => 'أرض',
                            'building' => 'عمارة',
                            'villa' => 'فيلا'
                        ];
                        echo $source_labels[$prefill_data['source_type']] ?? $prefill_data['source_type'];
                        ?>
                        رقم <?php echo $prefill_data['source_id']; ?>.
                        يمكنك مراجعة وتعديل أي معلومات قبل النشر.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Offer Form -->
    <form id="offerForm">
        <div class="row">
            <!-- Main Information Card -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            المعلومات الأساسية
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Property Type -->
                        <div class="mb-3">
                            <label class="form-label">نوع العقار <span class="text-danger">*</span></label>
                            <select class="form-select" id="property_type_id" name="property_type_id" required data-prefill-value="<?php echo isset($prefill_data['property_type_id']) ? intval($prefill_data['property_type_id']) : ''; ?>">
                                <option value="">اختر نوع العقار...</option>
                                <!-- Will be loaded via JavaScript -->
                            </select>
                        </div>

                        <!-- Arabic Title -->
                        <div class="mb-3">
                            <label class="form-label">العنوان بالعربي <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title_ar" name="title_ar"
                                   placeholder="مثال: شقة فاخرة للبيع في حي النرجس" maxlength="255" required
                                   value="<?php echo isset($prefill_data['title_ar']) ? htmlspecialchars($prefill_data['title_ar']) : ''; ?>">
                        </div>

                        <!-- English Title -->
                        <div class="mb-3">
                            <label class="form-label">العنوان بالإنجليزي</label>
                            <input type="text" class="form-control" id="title_en" name="title_en"
                                   placeholder="Example: Luxury apartment for sale in Al Narjis" maxlength="255">
                        </div>

                        <!-- Arabic Description -->
                        <div class="mb-3">
                            <label class="form-label">الوصف بالعربي <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description_ar" name="description_ar"
                                      rows="4" placeholder="وصف تفصيلي للعقار..." required><?php echo isset($prefill_data['description_ar']) ? htmlspecialchars($prefill_data['description_ar']) : ''; ?></textarea>
                        </div>

                        <!-- English Description -->
                        <div class="mb-3">
                            <label class="form-label">الوصف بالإنجليزي</label>
                            <textarea class="form-control" id="description_en" name="description_en"
                                      rows="4" placeholder="Detailed description..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Location Information Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-geo-alt me-2"></i>
                            معلومات الموقع
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Location -->
                        <div class="mb-3">
                            <label class="form-label">الموقع <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="location" name="location"
                                   placeholder="مثال: الرياض - حي النرجس" required
                                   value="<?php echo isset($prefill_data['location']) ? htmlspecialchars($prefill_data['location']) : ''; ?>">
                        </div>

                        <div class="row">
                            <!-- City -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المدينة <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" name="city"
                                       placeholder="مثال: الرياض" required
                                       value="<?php echo isset($prefill_data['city']) ? htmlspecialchars($prefill_data['city']) : ''; ?>">
                            </div>

                            <!-- District -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">الحي</label>
                                <input type="text" class="form-control" id="district" name="district"
                                       placeholder="مثال: النرجس"
                                       value="<?php echo isset($prefill_data['district']) ? htmlspecialchars($prefill_data['district']) : ''; ?>">
                            </div>
                        </div>

                        <!-- Street -->
                        <div class="mb-3">
                            <label class="form-label">الشارع</label>
                            <input type="text" class="form-control" id="street" name="street"
                                   placeholder="مثال: شارع التخصصي"
                                   value="<?php echo isset($prefill_data['street']) ? htmlspecialchars($prefill_data['street']) : ''; ?>">
                        </div>

                        <div class="row">
                            <!-- Latitude -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">خط العرض (Latitude)</label>
                                <input type="number" step="any" class="form-control" id="latitude" name="latitude"
                                       placeholder="24.7136"
                                       value="<?php echo isset($prefill_data['latitude']) && $prefill_data['latitude'] !== '' ? $prefill_data['latitude'] : ''; ?>">
                            </div>

                            <!-- Longitude -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">خط الطول (Longitude)</label>
                                <input type="number" step="any" class="form-control" id="longitude" name="longitude"
                                       placeholder="46.6753"
                                       value="<?php echo isset($prefill_data['longitude']) && $prefill_data['longitude'] !== '' ? $prefill_data['longitude'] : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Property Details Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-house me-2"></i>
                            تفاصيل العقار
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Area -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">المساحة (متر مربع)</label>
                                <input type="number" step="0.01" class="form-control" id="area" name="area"
                                       placeholder="180.5"
                                       value="<?php echo isset($prefill_data['area']) && $prefill_data['area'] !== '' ? $prefill_data['area'] : ''; ?>">
                            </div>

                            <!-- Building Age -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">عمر البناء (سنوات)</label>
                                <input type="number" class="form-control" id="building_age" name="building_age"
                                       placeholder="2">
                            </div>
                        </div>

                        <div class="row">
                            <!-- Bedrooms -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">غرف النوم</label>
                                <input type="number" class="form-control" id="bedrooms" name="bedrooms"
                                       placeholder="3" min="0"
                                       value="<?php echo isset($prefill_data['bedrooms']) && $prefill_data['bedrooms'] !== '' ? $prefill_data['bedrooms'] : ''; ?>">
                            </div>

                            <!-- Bathrooms -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">دورات المياه</label>
                                <input type="number" class="form-control" id="bathrooms" name="bathrooms"
                                       placeholder="2" min="0"
                                       value="<?php echo isset($prefill_data['bathrooms']) && $prefill_data['bathrooms'] !== '' ? $prefill_data['bathrooms'] : ''; ?>">
                            </div>

                            <!-- Floor Number -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">رقم الطابق</label>
                                <input type="number" class="form-control" id="floor_number" name="floor_number"
                                       placeholder="5" min="0"
                                       value="<?php echo isset($prefill_data['floor_number']) && $prefill_data['floor_number'] !== '' ? $prefill_data['floor_number'] : ''; ?>">
                            </div>
                        </div>

                        <!-- Amenities -->
                        <div class="mb-3">
                            <label class="form-label d-block">المرافق والمزايا</label>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="has_elevator" name="has_elevator" <?php echo isset($prefill_data['has_elevator']) && $prefill_data['has_elevator'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="has_elevator">
                                            مصعد
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="has_parking" name="has_parking" <?php echo isset($prefill_data['has_parking']) && $prefill_data['has_parking'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="has_parking">
                                            موقف سيارة
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="has_ac" name="has_ac">
                                        <label class="form-check-label" for="has_ac">
                                            مكيف
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="has_kitchen" name="has_kitchen" <?php echo isset($prefill_data['has_kitchen']) && $prefill_data['has_kitchen'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="has_kitchen">
                                            مطبخ
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_furnished" name="is_furnished">
                                        <label class="form-check-label" for="is_furnished">
                                            مفروش
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Price & Type Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="bi bi-cash-stack me-2"></i>
                            السعر ونوع العرض
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Offer Type -->
                        <div class="mb-3">
                            <label class="form-label">نوع العرض <span class="text-danger">*</span></label>
                            <select class="form-select" id="offer_type" name="offer_type" required>
                                <option value="SALE" <?php echo isset($prefill_data['offer_type']) && $prefill_data['offer_type'] === 'SALE' ? 'selected' : ''; ?>>للبيع</option>
                                <option value="RENT" <?php echo isset($prefill_data['offer_type']) && $prefill_data['offer_type'] === 'RENT' ? 'selected' : ''; ?>>للإيجار</option>
                                <option value="MORTGAGE" <?php echo isset($prefill_data['offer_type']) && $prefill_data['offer_type'] === 'MORTGAGE' ? 'selected' : ''; ?>>رهن</option>
                            </select>
                        </div>

                        <!-- Price -->
                        <div class="mb-3">
                            <label class="form-label">السعر (ريال) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price"
                                   placeholder="850000" required min="0"
                                   value="<?php echo isset($prefill_data['price']) && $prefill_data['price'] !== '' ? $prefill_data['price'] : ''; ?>">
                            <small class="text-muted">السعر بالريال السعودي</small>
                        </div>

                        <!-- Expiration Date -->
                        <div class="mb-3">
                            <label class="form-label">تاريخ انتهاء العرض</label>
                            <input type="date" class="form-control" id="expires_at" name="expires_at">
                            <small class="text-muted">اتركه فارغاً إذا لم يكن هناك تاريخ محدد</small>
                        </div>
                    </div>
                </div>

                <!-- AI Description Card -->
                <div class="card shadow-sm mb-4 border-primary">
                    <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #189c2aff 0%, #066d96ff 100%);">
                        <h6 class="mb-0">
                            <i class="bi bi-stars me-2"></i>
                            الذكاء الاصطناعي
                        </h6>
                    </div>
                    <div class="card-body">
                        <button type="button" class="btn w-100" id="aiDescriptionBtn" onclick="generateAIDescription()"
                                style="background: linear-gradient(135deg, #189c2aff 0%, #066d96ff 100%); color: white; border: none;">
                            <i class="bi bi-stars me-2"></i>
                            إنشاء وصف بالذكاء الاصطناعي
                        </button>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            سيقوم الذكاء الاصطناعي بإنشاء وصف احترافي للعقار
                        </small>
                        <div id="aiDescriptionStatus" class="mt-2" style="display: none;"></div>
                    </div>
                </div>

                <!-- Images Upload Card -->
                <div class="card shadow-sm mb-4 border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-images me-2"></i>
                            صور العقار
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- Upload Button -->
                        <div class="mb-3">
                            <label for="offerImages" class="btn btn-outline-info w-100" style="cursor: pointer;">
                                <i class="bi bi-cloud-upload me-2"></i>
                                اختر الصور
                            </label>
                            <input type="file" class="d-none" id="offerImages" name="images[]"
                                   accept="image/jpeg,image/jpg,image/png,image/webp" multiple
                                   onchange="handleImageSelect(event)">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i>
                                يمكنك اختيار حتى 10 صور (PNG, JPG, WEBP - الحد الأقصى 10MB لكل صورة)
                            </small>
                        </div>

                        <!-- Images Preview Container -->
                        <div id="imagesPreviewContainer" class="mt-3" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="text-muted">الصور المحددة:</strong>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAllImages()">
                                    <i class="bi bi-trash me-1"></i>حذف الكل
                                </button>
                            </div>
                            <div id="imagesPreview" class="row g-2">
                                <!-- Image previews will be inserted here -->
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-star-fill text-warning me-1"></i>
                                اضغط على صورة لجعلها الصورة الرئيسية
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Submit Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100 mb-2" id="submitBtn">
                            <i class="bi bi-check-circle me-2"></i>
                            نشر العرض
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100" onclick="window.location.href='index.php'">
                            <i class="bi bi-x-circle me-2"></i>
                            إلغاء
                        </button>
                    </div>
                </div>

                <!-- Map Card -->
                <div class="card shadow-sm mb-4 border-success" id="mapCard">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-map me-2"></i>
                            تحديد الموقع على الخريطة
                        </h6>
                        <button type="button" class="btn btn-sm btn-light" id="fullscreenBtn" onclick="toggleMapFullscreen()" title="ملء الشاشة">
                            <i class="bi bi-arrows-fullscreen"></i>
                        </button>
                    </div>
                    <div class="card-body p-2">
                        <div id="map" style="height: 300px; border-radius: 6px;"></div>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            اضغط على الخريطة لتحديد موقع العقار
                        </small>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="card shadow-sm border-info">
                    <div class="card-body">
                        <h6 class="text-info">
                            <i class="bi bi-info-circle me-2"></i>
                            ملاحظات هامة
                        </h6>
                        <ul class="small mb-0">
                            <li>الحقول المميزة بـ <span class="text-danger">*</span> إلزامية</li>
                            <li>تأكد من صحة جميع المعلومات قبل النشر</li>
                            <li>يمكنك تعديل العرض لاحقاً</li>
                            <li>الحد اليومي: 100 عرض</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- AI Hint Modal -->
<div class="modal fade" id="aiHintModal" tabindex="-1" aria-labelledby="aiHintModalLabel" aria-hidden="true" dir="rtl">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #189c2aff 0%, #066d96ff 100%); color: white;">
                <h5 class="modal-title" id="aiHintModalLabel">
                    <i class="bi bi-stars me-2"></i>إنشاء وصف بالذكاء الاصطناعي
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="aiHintInput" class="form-label">
                        <i class="bi bi-info-circle me-1"></i>أضف وصف مختصر أو ملاحظات إضافية (اختياري)
                    </label>
                    <textarea class="form-control" id="aiHintInput" rows="4"
                              placeholder="مثال: العقار في موقع هادئ، قريب من المدارس والمستشفيات، تشطيبات فاخرة..."></textarea>
                    <small class="text-muted">
                        سيساعد الذكاء الاصطناعي على إنشاء وصف أفضل بناءً على ملاحظاتك
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>إلغاء
                </button>
                <button type="button" class="btn btn-success" onclick="proceedWithAIGeneration()">
                    <i class="bi bi-stars me-1"></i>إنشاء الوصف
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Configuration
const API_BASE_URL = '<?php echo $API_BASE_URL; ?>';
const API_KEY = '<?php echo $api_key; ?>';

/**
 * Fetch property types from API
 */
const fetchPropertyTypes = async () => {
    try {
        const response = await fetch(`${API_BASE_URL}/property-types`);
        const result = await response.json();

        if (result.status === 'success' && result.data && Array.isArray(result.data)) {
            const selectEl = document.getElementById('property_type_id');
            const options = result.data
                .map(pt => `<option value="${pt.id}">${pt.name_ar} - ${pt.name_en}</option>`)
                .join('');
            selectEl.innerHTML = '<option value="">اختر نوع العقار...</option>' + options;

            // Set pre-filled value if available
            const prefillValue = selectEl.getAttribute('data-prefill-value');
            if (prefillValue && prefillValue !== '') {
                selectEl.value = prefillValue;
            }
        } else {
            showBootstrapNotification('فشل تحميل أنواع العقارات', 'error');
            console.error('Unexpected API response:', result);
        }
    } catch (error) {
        console.error('Error fetching property types:', error);
        showBootstrapNotification('خطأ في تحميل أنواع العقارات', 'error');
    }
};

/**
 * Handle form submission
 */
document.getElementById('offerForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const submitBtn = document.getElementById('submitBtn');
    const originalHTML = submitBtn.innerHTML;

    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جاري النشر...';

    try {
        // Collect form data
        const formData = new FormData(e.target);
        const offerData = {};

        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            // Handle checkboxes
            if (key.startsWith('has_') || key.startsWith('is_')) {
                offerData[key] = true;
            }
            // Handle numbers
            else if (['property_type_id', 'price', 'area', 'bedrooms', 'bathrooms', 'floor_number', 'building_age', 'latitude', 'longitude'].includes(key)) {
                if (value !== '') {
                    offerData[key] = parseFloat(value);
                }
            }
            // Handle strings
            else if (value !== '') {
                offerData[key] = value;
            }
        }

        // Add unchecked checkboxes as false
        ['has_elevator', 'has_parking', 'has_ac', 'has_kitchen', 'is_furnished'].forEach(field => {
            if (!(field in offerData)) {
                offerData[field] = false;
            }
        });

        console.log('Sending offer data:', offerData);

        // Send to API
        const response = await fetch(`${API_BASE_URL}/offers`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${API_KEY}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(offerData)
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            // Log the full response to debug offer ID extraction
            console.log('Create offer API response:', result);
            console.log('Response data:', result.data);

            // Try multiple possible locations for the offer ID
            const offerId = result.data?.offer_id || result.data?.offer?.id || result.data?.id;
            console.log('Extracted offer ID:', offerId);

            showBootstrapNotification('تم نشر العرض بنجاح!', 'success');

            // Upload images if any
            if (selectedImages.length > 0) {
                if (!offerId) {
                    console.error('Cannot upload images: offer ID is missing from response');
                    showBootstrapNotification('تحذير: لم يتم رفع الصور بسبب عدم توفر معرف العرض', 'warning');
                } else {
                    showBootstrapNotification('جاري رفع الصور...', 'info');
                    console.log('Starting image upload for offer ID:', offerId);
                    console.log('Number of images to upload:', selectedImages.length);
                    const imageUploadSuccess = await uploadOfferImages(offerId);
                    console.log('Image upload result:', imageUploadSuccess);

                    // Redirect after image upload completes
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1500);
                    return; // Exit early so we don't hit the redirect below
                }
            }

            // Redirect to offers list after 2 seconds (only if no images to upload)
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        } else {
            const errorMsg = result.message || 'فشل نشر العرض';
            showBootstrapNotification(errorMsg, 'error');
            console.error('API Error:', result);

            // Show validation errors if any
            if (result.errors) {
                Object.entries(result.errors).forEach(([field, message]) => {
                    console.error(`${field}: ${message}`);
                });
            }
        }
    } catch (error) {
        console.error('Error creating offer:', error);
        showBootstrapNotification('حدث خطأ أثناء نشر العرض', 'error');
    } finally {
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    }
});

// Map initialization
let map = null;
let marker = null;

// Image handling
let selectedImages = [];
let primaryImageIndex = 0;

/**
 * Handle image file selection
 */
const handleImageSelect = (event) => {
    const files = Array.from(event.target.files);

    if (files.length === 0) return;

    // Check total images limit (max 10 per offer according to API)
    const remainingSlots = 10 - selectedImages.length;
    if (remainingSlots === 0) {
        showBootstrapNotification('لا يمكن إضافة المزيد من الصور (الحد الأقصى 10 صور)', 'warning');
        return;
    }

    // Validate file types and sizes (10MB max according to API)
    const maxSize = 10 * 1024 * 1024; // 10MB
    const validFiles = files.slice(0, remainingSlots).filter(file => {
        if (file.size > maxSize) {
            showBootstrapNotification(`الملف ${file.name} كبير جداً (الحد الأقصى 10MB)`, 'warning');
            return false;
        }
        return true;
    });

    if (validFiles.length === 0) return;

    // Add to selected images
    validFiles.forEach(file => {
        selectedImages.push({
            file: file,
            preview: URL.createObjectURL(file),
            isPrimary: selectedImages.length === 0 // First image is primary by default
        });
    });

    // Update primary index if this is the first image
    if (primaryImageIndex === -1 && selectedImages.length > 0) {
        primaryImageIndex = 0;
    }

    renderImagePreviews();

    const message = validFiles.length < files.length
        ? `تم إضافة ${validFiles.length} صورة (تم تجاهل البعض بسبب القيود)`
        : `تم إضافة ${validFiles.length} صورة`;
    showBootstrapNotification(message, 'success');
};

/**
 * Render image previews
 */
const renderImagePreviews = () => {
    const container = document.getElementById('imagesPreview');
    const previewContainer = document.getElementById('imagesPreviewContainer');

    if (selectedImages.length === 0) {
        previewContainer.style.display = 'none';
        return;
    }

    previewContainer.style.display = 'block';

    const html = selectedImages.map((img, index) => {
        const isPrimary = index === primaryImageIndex;
        const borderClass = isPrimary ? 'border-warning border-3' : 'border-secondary';
        const badgeHtml = isPrimary ? '<span class="badge bg-warning position-absolute top-0 start-0 m-1"><i class="bi bi-star-fill"></i> رئيسية</span>' : '';

        return `
            <div class="col-6">
                <div class="position-relative">
                    <div class="card ${borderClass}" style="cursor: pointer;" onclick="setPrimaryImage(${index})">
                        <img src="${img.preview}" class="card-img-top" alt="Preview" style="height: 120px; object-fit: cover;">
                        ${badgeHtml}
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1"
                                onclick="event.stopPropagation(); removeImage(${index})">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <small class="text-muted d-block text-center mt-1 text-truncate">${img.file.name}</small>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = html;
};

/**
 * Set primary image
 */
const setPrimaryImage = (index) => {
    if (index >= 0 && index < selectedImages.length) {
        primaryImageIndex = index;
        selectedImages.forEach((img, i) => {
            img.isPrimary = i === index;
        });
        renderImagePreviews();
        showBootstrapNotification('تم تعيين الصورة الرئيسية', 'success');
    }
};

/**
 * Remove image
 */
const removeImage = (index) => {
    if (index >= 0 && index < selectedImages.length) {
        // Revoke object URL to free memory
        URL.revokeObjectURL(selectedImages[index].preview);

        // Remove from array
        selectedImages.splice(index, 1);

        // Update primary index
        if (primaryImageIndex === index) {
            primaryImageIndex = selectedImages.length > 0 ? 0 : -1;
        } else if (primaryImageIndex > index) {
            primaryImageIndex--;
        }

        // Update isPrimary flags
        selectedImages.forEach((img, i) => {
            img.isPrimary = i === primaryImageIndex;
        });

        renderImagePreviews();
        showBootstrapNotification('تم حذف الصورة', 'info');
    }
};

/**
 * Clear all images
 */
const clearAllImages = () => {
    // Revoke all object URLs
    selectedImages.forEach(img => URL.revokeObjectURL(img.preview));

    // Clear array
    selectedImages = [];
    primaryImageIndex = -1;

    // Reset file input
    document.getElementById('offerImages').value = '';

    renderImagePreviews();
    showBootstrapNotification('تم حذف جميع الصور', 'info');
};

/**
 * Upload images after offer is created
 */
const uploadOfferImages = async (offerId) => {
    console.log('[uploadOfferImages] Starting upload for offer:', offerId);
    console.log('[uploadOfferImages] Selected images count:', selectedImages.length);
    console.log('[uploadOfferImages] Selected images array:', selectedImages);

    if (selectedImages.length === 0) {
        console.log('[uploadOfferImages] No images to upload');
        return true;
    }

    try {
        const formData = new FormData();
        let filesAdded = 0;

        // Add images to FormData (API expects 'images' field - append multiple times with same name)
        selectedImages.forEach((img, index) => {
            console.log(`[uploadOfferImages] Processing image ${index}:`, img);

            if (img && img.file) {
                console.log(`[uploadOfferImages] Adding image ${index}:`, {
                    name: img.file.name,
                    size: img.file.size,
                    type: img.file.type,
                    constructor: img.file.constructor ? img.file.constructor.name : 'unknown'
                });

                // Append with same field name 'images' for each file (correct way for multiple files)
                formData.append('images[]', img.file);
                filesAdded++;
                console.log(`[uploadOfferImages] ✓ File ${index} appended successfully`);
            } else {
                console.error(`[uploadOfferImages] ✗ Invalid file at index ${index}:`, img);
            }
        });

        console.log('[uploadOfferImages] Total files added to FormData:', filesAdded);

        // Verify FormData has entries
        let formDataEntries = 0;
        for (let pair of formData.entries()) {
            formDataEntries++;
            console.log(`[uploadOfferImages] FormData entry #${formDataEntries}: ${pair[0]} =`, pair[1] instanceof File ? `File(${pair[1].name}, ${pair[1].size} bytes)` : pair[1]);
        }
        console.log('[uploadOfferImages] Total FormData entries:', formDataEntries);

        if (filesAdded === 0) {
            throw new Error('No valid image files to upload');
        }

        // Optional: Specify which image should be the primary one (0-based index)
        if (primaryImageIndex >= 0 && primaryImageIndex < selectedImages.length) {
            console.log('[uploadOfferImages] Setting primary image index:', primaryImageIndex);
            formData.append('primary_image_index', primaryImageIndex);
        }

        const uploadUrl = `${API_BASE_URL}/offers/${offerId}/images`;
        console.log('[uploadOfferImages] Uploading to:', uploadUrl);
        console.log('[uploadOfferImages] API Key:', API_KEY ? API_KEY.substring(0, 4) + '...' : 'MISSING');

        const response = await fetch(uploadUrl, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${API_KEY}`
                // Note: Do NOT set Content-Type header for multipart/form-data
            },
            body: formData
        });

        console.log('[uploadOfferImages] Response status:', response.status, response.statusText);
        console.log('[uploadOfferImages] Response headers:', Object.fromEntries(response.headers.entries()));

        if (!response.ok) {
            const contentType = response.headers.get('content-type');
            let errorData = {};

            if (contentType && contentType.includes('application/json')) {
                errorData = await response.json().catch(() => ({}));
            } else {
                const errorText = await response.text();
                console.error('[uploadOfferImages] Non-JSON error response:', errorText);
                errorData.message = `HTTP ${response.status}: ${errorText.substring(0, 200)}`;
            }

            console.error('[uploadOfferImages] Upload failed:', errorData);
            throw new Error(errorData.message || `فشل رفع الصور (HTTP ${response.status})`);
        }

        const result = await response.json();
        console.log('[uploadOfferImages] Upload successful:', result);

        if (result.status === 'success') {
            const uploadedCount = result.data?.uploaded || selectedImages.length;
            showBootstrapNotification(`تم رفع ${uploadedCount} صورة بنجاح!`, 'success');
            return true;
        } else {
            throw new Error(result.message || 'فشل رفع الصور');
        }
    } catch (error) {
        console.error('[uploadOfferImages] Exception caught:', error);
        console.error('[uploadOfferImages] Error stack:', error.stack);
        showBootstrapNotification(`تحذير: ${error.message}`, 'warning', 5000);
        return false;
    }
};

/**
 * Generate AI description for the offer
 */
const generateAIDescription = () => {
    // Get property details
    const propertyTypeSelect = document.getElementById('property_type_id');
    const titleAr = document.getElementById('title_ar').value.trim();

    // Validate minimum requirements
    if (!propertyTypeSelect.value || !titleAr) {
        showBootstrapNotification('يرجى ملء نوع العقار والعنوان على الأقل قبل إنشاء الوصف', 'warning');
        return;
    }

    // Clear previous hint
    document.getElementById('aiHintInput').value = '';

    // Show modal
    const aiHintModal = new bootstrap.Modal(document.getElementById('aiHintModal'));
    aiHintModal.show();
};

/**
 * Proceed with AI generation after getting user hint
 */
const proceedWithAIGeneration = async () => {
    const btn = document.getElementById('aiDescriptionBtn');
    const descriptionField = document.getElementById('description_ar');
    const statusDiv = document.getElementById('aiDescriptionStatus');
    const userHint = document.getElementById('aiHintInput').value.trim();

    // Close modal
    const aiHintModal = bootstrap.Modal.getInstance(document.getElementById('aiHintModal'));
    aiHintModal.hide();

    // Get property details
    const propertyTypeSelect = document.getElementById('property_type_id');
    const titleAr = document.getElementById('title_ar').value.trim();
    const location = document.getElementById('location').value.trim();
    const city = document.getElementById('city').value.trim();
    const district = document.getElementById('district').value.trim();
    const offerTypeSelect = document.getElementById('offer_type');
    const price = document.getElementById('price').value;
    const area = document.getElementById('area').value;
    const bedrooms = document.getElementById('bedrooms').value;
    const bathrooms = document.getElementById('bathrooms').value;

    // Get property type text
    const propertyTypeText = propertyTypeSelect.options[propertyTypeSelect.selectedIndex]?.text || '';
    const offerTypeText = offerTypeSelect.options[offerTypeSelect.selectedIndex]?.text || '';

    // Disable button and show loading
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جاري الإنشاء...';
    statusDiv.style.display = 'block';
    statusDiv.className = 'mt-2 text-info';
    statusDiv.innerHTML = '<i class="bi bi-stars me-1"></i>الذكاء الاصطناعي يعمل...';

    try {
        // Build comprehensive property description
        let propertyDetails = `نوع العقار: ${propertyTypeText}\n`;
        propertyDetails += `نوع العرض: ${offerTypeText}\n`;
        propertyDetails += `العنوان: ${titleAr}\n`;

        if (location) propertyDetails += `الموقع: ${location}\n`;
        if (city) propertyDetails += `المدينة: ${city}\n`;
        if (district) propertyDetails += `الحي: ${district}\n`;
        if (price) propertyDetails += `السعر: ${price} ريال\n`;
        if (area) propertyDetails += `المساحة: ${area} متر مربع\n`;
        if (bedrooms) propertyDetails += `غرف النوم: ${bedrooms}\n`;
        if (bathrooms) propertyDetails += `دورات المياه: ${bathrooms}\n`;

        // Add amenities
        const amenities = [];
        if (document.getElementById('has_elevator').checked) amenities.push('مصعد');
        if (document.getElementById('has_parking').checked) amenities.push('موقف سيارة');
        if (document.getElementById('has_ac').checked) amenities.push('مكيف');
        if (document.getElementById('has_kitchen').checked) amenities.push('مطبخ');
        if (document.getElementById('is_furnished').checked) amenities.push('مفروش');

        if (amenities.length > 0) {
            propertyDetails += `المرافق: ${amenities.join(', ')}\n`;
        }

        if (userHint) {
            propertyDetails += `\nملاحظات إضافية: ${userHint}`;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('property_type', propertyTypeText);
        formData.append('property_name', titleAr);
        formData.append('property_address', location);
        formData.append('existing_description', propertyDetails);
        formData.append('style', 'professional');

        // Make AJAX request to AI backend
        const response = await fetch('../ai/features/property_description.hnt', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            // Set the generated description
            descriptionField.value = data.description;

            // Show success status
            statusDiv.className = 'mt-2 text-success';
            statusDiv.innerHTML = `<i class="bi bi-check-circle me-1"></i>تم الإنشاء بنجاح! (${data.stats?.tokens || 0} كلمة)`;

            showBootstrapNotification('تم إنشاء الوصف بنجاح!', 'success');

            // Hide status after 5 seconds
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 5000);
        } else {
            throw new Error(data.message || 'فشل في إنشاء الوصف');
        }
    } catch (error) {
        console.error('AI Error:', error);

        // Show error status
        statusDiv.className = 'mt-2 text-danger';
        statusDiv.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>${error.message}`;

        showBootstrapNotification('خطأ: ' + error.message, 'error');

        // Hide error after 8 seconds
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 8000);
    } finally {
        // Reset button
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-stars me-2"></i>إنشاء وصف بالذكاء الاصطناعي';
    }
};

/**
 * Toggle map fullscreen mode
 */
const toggleMapFullscreen = () => {
    const mapCard = document.getElementById('mapCard');
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const isFullscreen = mapCard.classList.contains('fullscreen');

    if (isFullscreen) {
        // Exit fullscreen
        mapCard.classList.remove('fullscreen');
        fullscreenBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        fullscreenBtn.title = 'ملء الشاشة';
    } else {
        // Enter fullscreen
        mapCard.classList.add('fullscreen');
        fullscreenBtn.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
        fullscreenBtn.title = 'خروج من ملء الشاشة';
    }

    // Force map to recalculate size after transition
    setTimeout(() => {
        if (map) {
            map.invalidateSize();
        }
    }, 100);
};

const initMap = () => {
    const defaultLat = <?php echo $mainlat; ?>;
    const defaultLng = <?php echo $mainlng; ?>;

    // Initialize map
    map = L.map('map').setView([defaultLat, defaultLng], 13);

    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    // Add click event to map
    map.on('click', (e) => {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        // Update form fields
        document.getElementById('latitude').value = lat.toFixed(6);
        document.getElementById('longitude').value = lng.toFixed(6);

        // Remove existing marker if any
        if (marker) {
            map.removeLayer(marker);
        }

        // Add new marker
        marker = L.marker([lat, lng], {
            draggable: true
        }).addTo(map);

        // Update coordinates when marker is dragged
        marker.on('dragend', (event) => {
            const position = marker.getLatLng();
            document.getElementById('latitude').value = position.lat.toFixed(6);
            document.getElementById('longitude').value = position.lng.toFixed(6);
        });

        // Show popup
        marker.bindPopup(`<b>الموقع المحدد</b><br>خط العرض: ${lat.toFixed(6)}<br>خط الطول: ${lng.toFixed(6)}`).openPopup();
    });

    // If latitude/longitude fields have values, show marker
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    const updateMapFromInputs = () => {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);

        if (!isNaN(lat) && !isNaN(lng)) {
            // Remove existing marker
            if (marker) {
                map.removeLayer(marker);
            }

            // Add marker at coordinates
            marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);

            // Center map on marker
            map.setView([lat, lng], 15);

            // Update coordinates when marker is dragged
            marker.on('dragend', (event) => {
                const position = marker.getLatLng();
                latInput.value = position.lat.toFixed(6);
                lngInput.value = position.lng.toFixed(6);
            });

            marker.bindPopup(`<b>الموقع المحدد</b><br>خط العرض: ${lat.toFixed(6)}<br>خط الطول: ${lng.toFixed(6)}`);
        }
    };

    // Update map when inputs change
    latInput.addEventListener('change', updateMapFromInputs);
    lngInput.addEventListener('change', updateMapFromInputs);

    // Initial check
    updateMapFromInputs();
};

// Load property types on page load
document.addEventListener('DOMContentLoaded', () => {
    fetchPropertyTypes();

    // Initialize map
    initMap();

    // Set minimum date for expiration to today
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    document.getElementById('expires_at').setAttribute('min', todayStr);

    // Set default expiration date to +3 months
    const threeMonthsLater = new Date();
    threeMonthsLater.setMonth(threeMonthsLater.getMonth() + 3);
    const defaultExpiry = threeMonthsLater.toISOString().split('T')[0];
    document.getElementById('expires_at').value = defaultExpiry;

    // Add ESC key handler to exit fullscreen
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const mapCard = document.getElementById('mapCard');
            if (mapCard.classList.contains('fullscreen')) {
                toggleMapFullscreen();
            }
        }
    });
});
</script>

<?php
include("../../footer.hnt");
?>
