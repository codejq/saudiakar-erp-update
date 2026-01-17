<?php
// Offer Details Page
$sc_title = "تفاصيل العرض";
$sc_id = "72";
$canscroll = 1;

include_once("../../nocash.hnt");
include("../../header.hnt");
require_once("setup.php");

// Get offer ID from URL
$offer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($offer_id <= 0) {
    echo '<div class="container mt-5"><div class="alert alert-danger">رقم العرض غير صحيح</div></div>';
    include("../../footer.hnt");
    exit;
}

// Fetch offer details
$query = "SELECT
    o.*,
    u.office_name_ar,
    u.office_name_en,
    u.phone,
    u.email,
    u.city as office_city,
    u.address as office_address,
    pt.name_ar as property_type_ar,
    pt.name_en as property_type_en
FROM offers o
INNER JOIN users u ON o.user_id = u.id
INNER JOIN property_types pt ON o.property_type_id = pt.id
WHERE o.id = {$offer_id}";

$result = mysql_query($query, $offers_link);

if (!$result || mysql_num_rows($result) === 0) {
    echo '<div class="container mt-5"><div class="alert alert-danger">العرض غير موجود</div></div>';
    include("../../footer.hnt");
    exit;
}

$offer = mysql_fetch_assoc($result);

// API Configuration for messaging
$API_BASE_URL = 'https://api.saudiakar.net';
require_once("ApiKey.php");

$api_key = getApiKey();

// Fetch offer images from local database
$images_query = "SELECT * FROM offer_images WHERE offer_id = {$offer_id} ORDER BY display_order ASC, is_primary DESC";
$images_result = mysql_query($images_query, $offers_link);
$images = [];
while ($img = mysql_fetch_assoc($images_result)) {
    $images[] = $img;
}

// Translation maps
$offer_types = [
    'SALE' => 'بيع',
    'RENT' => 'إيجار',
    'MORTGAGE' => 'رهن'
];

$status_labels = [
    'ACTIVE' => ['text' => 'نشط', 'class' => 'success'],
    'SOLD' => ['text' => 'مباع', 'class' => 'secondary'],
    'PENDING' => ['text' => 'قيد الانتظار', 'class' => 'warning'],
    'EXPIRED' => ['text' => 'منتهي', 'class' => 'danger']
];
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin=""/>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">العروض العقارية</a></li>
            <li class="breadcrumb-item active" aria-current="page">عرض #<?php echo $offer_id; ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Offer Header -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="mb-2"><?php echo htmlspecialchars($offer['title_ar']); ?></h2>
                            <?php if (!empty($offer['title_en'])): ?>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($offer['title_en']); ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-<?php echo $status_labels[$offer['status']]['class']; ?> fs-6">
                            <?php echo $status_labels[$offer['status']]['text']; ?>
                        </span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-auto">
                            <span class="badge bg-primary"><?php echo $offer_types[$offer['offer_type']]; ?></span>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-info"><?php echo htmlspecialchars($offer['property_type_ar']); ?></span>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-geo-alt-fill text-danger"></i>
                            <span><?php echo htmlspecialchars($offer['city']); ?></span>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-eye-fill text-secondary"></i>
                            <span><?php echo number_format($offer['views_count']); ?> مشاهدة</span>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-telephone-fill text-success"></i>
                            <span><?php echo number_format($offer['contact_count']); ?> اتصال</span>
                        </div>
                    </div>

                    <div class="alert alert-success mb-0">
                        <h3 class="mb-0">
                            <strong><?php echo number_format($offer['price'], 2); ?> ريال</strong>
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Images Gallery -->
            <?php if (count($images) > 0): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">صور العرض (<?php echo count($images); ?>)</h5>
                </div>
                <div class="card-body">
                    <div id="offerImagesCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-indicators">
                            <?php foreach ($images as $index => $img): ?>
                                <button type="button" data-bs-target="#offerImagesCarousel"
                                        data-bs-slide-to="<?php echo $index; ?>"
                                        <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?>
                                        aria-label="Slide <?php echo $index + 1; ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="carousel-inner">
                            <?php foreach ($images as $index => $img): ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>"
                                         class="d-block w-100"
                                         alt="صورة العرض <?php echo $index + 1; ?>"
                                         style="max-height: 500px; object-fit: contain; background-color: #f8f9fa;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#offerImagesCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">السابق</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#offerImagesCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">التالي</span>
                        </button>
                    </div>

                    <!-- Thumbnails -->
                    <div class="row g-2 mt-3">
                        <?php foreach ($images as $index => $img): ?>
                            <div class="col-2">
                                <img src="<?php echo htmlspecialchars($img['thumbnail_path'] ?? $img['image_path']); ?>"
                                     class="img-thumbnail cursor-pointer"
                                     onclick="bootstrap.Carousel.getInstance(document.getElementById('offerImagesCarousel')).to(<?php echo $index; ?>)"
                                     style="cursor: pointer; height: 80px; object-fit: cover;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Description -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">الوصف</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($offer['description_ar']); ?></p>
                    <?php if (!empty($offer['description_en'])): ?>
                        <hr>
                        <p class="text-muted mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($offer['description_en']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Property Details -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">تفاصيل العقار</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if (!empty($offer['area'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-rulers fs-4 text-primary me-3"></i>
                                <div>
                                    <small class="text-muted d-block">المساحة</small>
                                    <strong><?php echo number_format($offer['area'], 2); ?> م²</strong>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($offer['bedrooms'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-door-open fs-4 text-primary me-3"></i>
                                <div>
                                    <small class="text-muted d-block">غرف النوم</small>
                                    <strong><?php echo $offer['bedrooms']; ?> غرفة</strong>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($offer['bathrooms'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-droplet fs-4 text-primary me-3"></i>
                                <div>
                                    <small class="text-muted d-block">دورات المياه</small>
                                    <strong><?php echo $offer['bathrooms']; ?> دورة</strong>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($offer['floor_number'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-building fs-4 text-primary me-3"></i>
                                <div>
                                    <small class="text-muted d-block">رقم الطابق</small>
                                    <strong>الطابق <?php echo $offer['floor_number']; ?></strong>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($offer['building_age'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-check fs-4 text-primary me-3"></i>
                                <div>
                                    <small class="text-muted d-block">عمر البناء</small>
                                    <strong><?php echo $offer['building_age']; ?> سنة</strong>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Amenities -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">المميزات</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <?php if ($offer['has_elevator']): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger fs-5 me-2"></i>
                                <?php endif; ?>
                                <span>مصعد</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <?php if ($offer['has_parking']): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger fs-5 me-2"></i>
                                <?php endif; ?>
                                <span>موقف سيارات</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <?php if ($offer['has_ac']): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger fs-5 me-2"></i>
                                <?php endif; ?>
                                <span>تكييف</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <?php if ($offer['has_kitchen']): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger fs-5 me-2"></i>
                                <?php endif; ?>
                                <span>مطبخ</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <?php if ($offer['is_furnished']): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger fs-5 me-2"></i>
                                <?php endif; ?>
                                <span>مفروش</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">الموقع</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th width="30%">المدينة</th>
                                <td><?php echo htmlspecialchars($offer['city']); ?></td>
                            </tr>
                            <?php if (!empty($offer['district'])): ?>
                            <tr>
                                <th>الحي</th>
                                <td><?php echo htmlspecialchars($offer['district']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($offer['street'])): ?>
                            <tr>
                                <th>الشارع</th>
                                <td><?php echo htmlspecialchars($offer['street']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>العنوان</th>
                                <td><?php echo htmlspecialchars($offer['location']); ?></td>
                            </tr>
                            <?php if (!empty($offer['latitude']) && !empty($offer['longitude'])): ?>
                            <tr>
                                <th>الإحداثيات</th>
                                <td>
                                    <a href="https://www.google.com/maps?q=<?php echo $offer['latitude']; ?>,<?php echo $offer['longitude']; ?>"
                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-map"></i> عرض على الخريطة
                                    </a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Office Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">معلومات المكتب</h5>
                </div>
                <div class="card-body">
                    <h6 class="mb-3"><?php echo htmlspecialchars($offer['office_name_ar']); ?></h6>
                    <?php if (!empty($offer['office_name_en'])): ?>
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($offer['office_name_en']); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($offer['phone'])): ?>
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-telephone-fill text-success me-2"></i>
                        <a href="tel:<?php echo htmlspecialchars($offer['phone']); ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($offer['phone']); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($offer['email'])): ?>
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-envelope-fill text-primary me-2"></i>
                        <a href="mailto:<?php echo htmlspecialchars($offer['email']); ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($offer['email']); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($offer['office_city'])): ?>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                        <span><?php echo htmlspecialchars($offer['office_city']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Map Card -->
            <?php if (!empty($offer['latitude']) && !empty($offer['longitude'])): ?>
            <div class="card shadow-sm mb-4" id="mapCard">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-map me-2"></i>
                        موقع العقار
                    </h5>
                    <button type="button" class="btn btn-sm btn-light" id="fullscreenBtn" onclick="toggleMapFullscreen()" title="ملء الشاشة">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>
                </div>
                <div class="card-body p-2">
                    <div id="offerMap" style="height: 300px; border-radius: 6px;"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Additional Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">معلومات إضافية</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th>رقم العرض</th>
                                <td>#<?php echo $offer['id']; ?></td>
                            </tr>
                            <tr>
                                <th>تاريخ النشر</th>
                                <td><?php echo date('Y-m-d', strtotime($offer['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>آخر تحديث</th>
                                <td><?php echo date('Y-m-d', strtotime($offer['updated_at'])); ?></td>
                            </tr>
                            <?php if (!empty($offer['expires_at'])): ?>
                            <tr>
                                <th>تاريخ الانتهاء</th>
                                <td><?php echo date('Y-m-d', strtotime($offer['expires_at'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Contact Seller -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-envelope me-2"></i>
                        تواصل مع المعلن
                    </h5>
                </div>
                <div class="card-body">
                    <form id="contactForm" onsubmit="sendMessage(event)">
                        <div class="mb-3">
                            <label for="messageSubject" class="form-label">الموضوع</label>
                            <input type="text" class="form-control" id="messageSubject"
                                   placeholder="استفسار عن العقار" value="استفسار عن: <?php echo htmlspecialchars(mb_substr($offer['title_ar'], 0, 100, 'UTF-8')); ?>...">
                        </div>
                        <div class="mb-3">
                            <label for="messageText" class="form-label">الرسالة</label>
                            <textarea class="form-control" id="messageText" rows="4"
                                      placeholder="اكتب رسالتك هنا..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-info w-100" id="sendMessageBtn">
                            <i class="bi bi-send me-2"></i>
                            إرسال الرسالة
                        </button>
                    </form>
                    <div id="messageAlert" class="mt-3" style="display: none;"></div>

                    <hr class="my-3">

                    <a href="index.php?user_id=<?php echo $offer['user_id']; ?>" class="btn btn-outline-info w-100">
                        <i class="bi bi-building me-2"></i>
                        عرض جميع عروض هذا المكتب
                    </a>
                </div>
            </div>

            <!-- Actions -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <a href="index.php" class="btn btn-secondary w-100 mb-2">
                        <i class="bi bi-arrow-right"></i> رجوع للقائمة
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-primary w-100">
                        <i class="bi bi-printer"></i> طباعة
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .breadcrumb, .card-actions, button, .btn {
        display: none !important;
    }
}

/* Map Fullscreen Styles */
#mapCard.fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    width: 100vw !important;
    height: 100vh !important;
    margin: 0 !important;
    border-radius: 0 !important;
    max-width: 100% !important;
}

#mapCard.fullscreen .card-body {
    height: calc(100% - 56px);
    padding: 0 !important;
}

#mapCard.fullscreen #offerMap {
    height: 100% !important;
    border-radius: 0 !important;
}

#mapCard.fullscreen .card-header {
    border-radius: 0 !important;
}
</style>

<?php if (!empty($offer['latitude']) && !empty($offer['longitude'])): ?>
<script>
let offerMap;

/**
 * Toggle map fullscreen mode
 */
function toggleMapFullscreen() {
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
        offerMap.invalidateSize();
    }, 100);
}

document.addEventListener('DOMContentLoaded', function() {
    const lat = <?php echo floatval($offer['latitude']); ?>;
    const lng = <?php echo floatval($offer['longitude']); ?>;

    // Initialize map
    offerMap = L.map('offerMap').setView([lat, lng], 15);

    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(offerMap);

    // Add marker for the offer location
    const marker = L.marker([lat, lng]).addTo(offerMap);

    // Add popup with offer title
    marker.bindPopup(`
        <div style="text-align: center;">
            <strong><?php echo htmlspecialchars($offer['title_ar']); ?></strong><br>
            <small><?php echo htmlspecialchars($offer['city']); ?></small>
        </div>
    `).openPopup();

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
<?php endif; ?>

<script>
// API Configuration
const API_BASE_URL = '<?php echo $API_BASE_URL; ?>';
const API_KEY = '<?php echo $api_key; ?>';
const OFFER_ID = <?php echo $offer_id; ?>;
const RECEIVER_ID = <?php echo $offer['user_id']; ?>;

/**
 * Send message to offer owner
 */
async function sendMessage(event) {
    event.preventDefault();

    const subject = document.getElementById('messageSubject').value.trim();
    const message = document.getElementById('messageText').value.trim();
    const sendBtn = document.getElementById('sendMessageBtn');
    const alertDiv = document.getElementById('messageAlert');

    if (!message) {
        showAlert('الرجاء كتابة رسالة', 'warning');
        return;
    }

    // Disable button
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جاري الإرسال...';

    try {
        const response = await fetch(`${API_BASE_URL}/messages`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${API_KEY}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                receiver_id: RECEIVER_ID,
                offer_id: OFFER_ID,
                subject: subject,
                message: message
            })
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            showAlert('تم إرسال الرسالة بنجاح! سيتم الرد عليك قريباً.', 'success');
            // Clear the message text
            document.getElementById('messageText').value = '';
        } else {
            throw new Error(result.message || 'فشل إرسال الرسالة');
        }
    } catch (error) {
        console.error('Error sending message:', error);
        showAlert('حدث خطأ أثناء إرسال الرسالة: ' + error.message, 'danger');
    } finally {
        // Re-enable button
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send me-2"></i>إرسال الرسالة';
    }
}

/**
 * Show alert message
 */
function showAlert(message, type) {
    const alertDiv = document.getElementById('messageAlert');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.display = 'block';

    // Auto-hide after 5 seconds
    setTimeout(() => {
        alertDiv.style.display = 'none';
    }, 5000);
}
</script>

<?php include("../../footer.hnt"); ?>
