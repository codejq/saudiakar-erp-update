<?php
// Arady (أراضي) report layout partial
?>
<div class="card shadow-sm border-0 mb-4">
	<div class="card-header text-white" style="background: linear-gradient(135deg, #189c2aff 0%, #066d96ff 100%);">
		<h5 class="mb-0">
			<i class="bi bi-<?= $is_editing ? 'pencil-square' : 'file-earmark-plus' ?> me-2"></i>
			<?= $is_editing ? 'تعديل تقرير أراضي' : 'إنشاء تقرير أراضي مخصص' ?>
		</h5>
	</div>
	<div class="card-body">
		<form action="custumreport.hnt" method="post" class="report-form">
			<input type="hidden" name="viewreport" value="<?= $viewreport; ?>">
			<input type="hidden" name="sqlfeilds" id="sqlfeilds">
			<input type="hidden" name="reporttype" value="arady">
			<?php if ($is_editing) { ?>
			<input type="hidden" name="editreportid" value="<?= $edit_report['id']; ?>">
			<?php } ?>

			<!-- Report Name -->
			<div class="mb-4">
				<label class="form-label fw-bold">
					<i class="bi bi-pencil me-2"></i>
					عنوان تقرير الأراضي:
				</label>
				<input type="text"
					   name="reportname"
					   class="form-control form-control-lg"
					   value="<?= htmlspecialchars($report_name, ENT_QUOTES, 'UTF-8'); ?>"
					   maxlength="30"
					   required>
			</div>

			<!-- Field Selector Section -->
			<div class="row mb-4">
				<div class="col-12">
					<h6 class="fw-bold mb-3">
						<i class="bi bi-list-check me-2"></i>
						اختر الحقول للتقرير:
					</h6>
				</div>
			</div>

			<div class="row g-3 mb-4">
				<!-- Available Fields -->
				<div class="col-md-5">
					<label class="form-label fw-bold">الحقول المتاحة</label>
					<select multiple size="10" class="form-select" name="sel1" id="sel1_arady" style="height: 300px;">
						<option value="(SELECT city.cityname FROM city INNER JOIN mokhatat city_mokhatat ON city.cityid=city_mokhatat.cityid WHERE city_mokhatat.mokhatatid=arady.mokhatatid LIMIT 1)">المدينة</option>
						<option value='ardtype'>نوع الارض
						<option value='st_date'>التاريخ
						<option value='custummerid'>مُدخل البيانات
						<option value='hay'>الحي
						<option value='en_date'>لوحة
						<option value='mobasher'>مباشر ام لا
						<option value='mesaha'>المساحة
						<option value='al3ard'>العرض
						<option value='tol'>الطول
						<option value='s1'>مياه
						<option value='s2'>كهرباء
						<option value='s3'>سفلت
						<option value='s4'>مدارس
						<option value='s5'>مستشفى
						<option value='s6'>تطل على ساحة
						<option value='s7'>جوار مسجد
						<option value='s8'>جوار شركات
						<option value='s9'>جوار مزرعة
						<option value='s10'>هاتف
						<option value='s11'>البيع بالقطعة
						<option value='s12'>البيع بالمتر المربع
						<option value='s13'>علي الشور
						<option value='s14'>قابل للتفاوض
						<option value='malekbayan1'>اسم المالك
						<option value='malekbayan2'>جوال المالك
						<option value='malekbayan3'>عنوان المالك
						<option value='malekbayan4'>رقم الصك الحالي
						<option value='malekbayan5'>هاتف منزل المالك
						<option value='malekbayan6'>هاتف عمل المالك
						<option value='malekbayan7'>صندوق بريد المالك
						<option value='malekbayan8'>بريد إلكتروني المالك
						<option value='malekbayan9'>جهة عمل المالك
						<option value='malekbayan10'>ملحوظات اضافية للمالك
						<option value='oldmalekbayan1'>اسم المالك سابق
						<option value='oldmalekbayan2'>جوال المالك سابق
						<option value='oldmalekbayan3'>عنوان المالك سابق
						<option value='oldmalekbayan4'>رقم الصك سابق
						<option value='oldmalekbayan5'>هاتف منزل المالك سابق
						<option value='oldmalekbayan6'>هاتف عمل المالك سابق
						<option value='oldmalekbayan7'>صندوق بريد المالك سابق
						<option value='oldmalekbayan8'>بريد إلكتروني سابق
						<option value='oldmalekbayan9'>جهة عمل المالك سابق
						<option value='oldmalekbayan10'>ملحوظات اضافية للمالك سابق
						<option value='secretinfo'>بيانات سرية
						<option value='statusx'>الحالة
						<option value='reversedby'>محجوزة عن طريق
						<option value='reversedfor'>محجوزة لـ
						<option value='soldto'>مباعة لـ
						<option value='share3'>الشارع الرئيسي
						<option value='share3f'>الشارع الفرعي
						<option value='meterprice'>سعر المتر
						<option value='tamweelmethod'>طريقة التمويل
						<option value='daf3method'>طريقة الدفع
						<option value='khdma'>الخدمة المطلوبة
						<option value='khazina'>خزينة
						<option value='dorg'>درج
						<option value='raf'>رف
						<option value='paperfile'>ملف ورقي
					</select>
				</div>

				<!-- Move Buttons -->
				<div class="col-md-2 d-flex flex-column justify-content-center align-items-center" style="padding-top: 2rem;">
					<button type="button" class="btn btn-success mb-2" onclick="moveOptions(this.form.sel1, this.form.sel2);warivaluetotxt(this.form.sel2,this.form.sqlfeilds);" title="إضافة الحقول المحددة" style="width: 60px; height: 45px;">
						<i class="bi bi-arrow-left fs-5"></i>
					</button>
					<button type="button" class="btn btn-warning" onclick="moveOptions(this.form.sel2, this.form.sel1);warivaluetotxt(this.form.sel2,this.form.sqlfeilds);" title="إزالة الحقول المحددة" style="width: 60px; height: 45px;">
						<i class="bi bi-arrow-right fs-5"></i>
					</button>
				</div>

				<!-- Selected Fields -->
				<div class="col-md-5">
					<label class="form-label fw-bold">الحقول بالتقرير</label>
					<select multiple size="10" class="form-select" name="sel2" id="sel2_arady" style="height: 300px;">
						<option value='ardid'>الرقم بالبرنامج</option>
						<option value='ardrakam'>رقم الارض</option>
						<option value='block'>رقم البلك</option>
						<option value='mokhatatid'>رقم المخطط</option>
						<option value='hedod'>الحدود</option>
						<option value='price'>السعر حد</option>
						<option value='pricesom'>السعر سوم</option>
						<option value='som'>سوم</option>
						<option value='masderid'>المصدر</option>
						<option value='note'>ملاحظات</option>
					</select>
				</div>
			</div>

			<!-- Sorting Section -->
			<div class="row g-3 mb-4">
				<div class="col-md-6">
					<label class="form-label fw-bold">ترتيب النتائج حسب</label>
					<select class="form-select" name="reportorderby" id="reportorderby_arady">
						<option value='ardid'>الرقم بالبرنامج
						<option value='ardrakam'>رقم الارض
						<option value='block'>رقم البلك
						<option value='mokhatatid'>رقم المخطط
						<option value="(SELECT city.cityname FROM city INNER JOIN mokhatat city_mokhatat ON city.cityid=city_mokhatat.cityid WHERE city_mokhatat.mokhatatid=arady.mokhatatid LIMIT 1)">المدينة
						<option value='hedod'>الحدود
						<option value='price'>السعر حد
						<option value='pricesom'>السعر سوم
						<option value='som'>سوم
						<option value='masderid'>المصدر
						<option value='ardtype'>نوع الارض
						<option value='st_date'>التاريخ
						<option value='custummerid'>مُدخل البيانات
						<option value='hay'>الحي
						<option value='note'>ملاحظات
						<option value='mobasher'>مباشر ام لا
						<option value='mesaha'>المساحة
						<option value='al3ard'>العرض
						<option value='tol'>الطول
						<option value='s1'>مياه
						<option value='s2'>كهرباء
						<option value='s3'>سفلت
						<option value='s4'>مدارس
						<option value='s5'>مستشفى
						<option value='s6'>تطل على ساحة
						<option value='s7'>جوار مسجد
						<option value='s8'>جوار شركات
						<option value='s9'>جوار مزرعة
						<option value='s10'>هاتف
						<option value='s11'>البيع بالقطعة
						<option value='s12'>البيع بالمتر المربع
						<option value='s13'>علي الشور
						<option value='s14'>قابل للتفاوض
						<option value='malekbayan1'>اسم المالك
						<option value='malekbayan2'>الجوال
						<option value='malekbayan3'>العنوان
						<option value='malekbayan4'>رقم الصك
						<option value='malekbayan5'>بيان مالك إضافي
						<option value='malekbayan6'>بيان ماك إضافي
						<option value='oldmalekbayan1'>مالك سابق
						<option value='oldmalekbayan2'> جوال سابق
						<option value='oldmalekbayan3'>عنوان مالك سابق
						<option value='oldmalekbayan4'>رقم صك سابق
						<option value='oldmalekbayan5'>بيان مالك سابق إضافي
						<option value='oldmalekbayan6'>بيان ماك سابق إضافي
						<option value='secretinfo'>بيانات سرية
						<option value='statusx'>الحالة
						<option value='reversedby'>محجوزة عن طريق
						<option value='reversedfor'>محجوزة لـ
						<option value='soldto'>مباعة لـ
					</select>
				</div>
				<div class="col-md-6">
					<label class="form-label fw-bold">ترتيب</label>
					<select class="form-select" name="reportdaesc" id="reportdaesc_arady">
						<option value="desc" <?= $report_daesc == 'desc' ? 'selected' : '' ?>>تنازلي</option>
						<option value="asc" <?= $report_daesc == 'asc' ? 'selected' : '' ?>>تصاعدي</option>
					</select>
				</div>
			</div>

			<?php if ($is_editing) { ?>
			<script>
			$(document).ready(function() {
				var selectedFields = <?= json_encode($selected_fields) ?>;
				var orderBy = '<?= $report_orderby ?>';
				$('#sel1_arady option').each(function() {
					if (selectedFields.indexOf($(this).val()) !== -1) {
						$(this).appendTo('#sel2_arady');
					}
				});
				if (orderBy) {
					$('#reportorderby_arady').val(orderBy);
				}
				warivaluetotxt(document.getElementById('sel2_arady'), document.getElementById('sqlfeilds'));
			});
			</script>
			<?php } ?>

			<?php
			$sql = "SELECT * FROM `custumreport` WHERE reporttype='arady' ORDER BY id DESC";
			$result = mysql_query($sql, $link);
			$numr = mysql_num_rows($result) - 4;
			?>
			<div class="row">
				<div class="col-12">
					<div class="card border-0 shadow-sm">
						<div class="card-header bg-light">
							<h6 class="mb-0">
								<i class="bi bi-folder2-open me-2"></i>
								التقارير الموجودة حاليًا
							</h6>
						</div>
						<div class="card-body" style="max-height: 900px; overflow-y: auto;">
							<div class="list-group list-group-flush">
								<?php
								$i = 0;
								while ($row = mysql_fetch_array($result)) {
								?>
								<div class="list-group-item d-flex justify-content-between align-items-center">
									<span><?= htmlspecialchars($row['reportname'], ENT_QUOTES, 'UTF-8'); ?></span>
									<div class="btn-group btn-group-sm">
										<a href="custumreport.hnt?viewreport=<?= $viewreport; ?>&editreport=<?= $row['id']; ?>"
										   class="btn btn-info btn-sm"
										   title="تعديل التقرير">
											<i class="bi bi-pencil"></i> تعديل
										</a>
										<button type="button"
												class="btn btn-danger btn-sm <?= "policy_" . $sc_id . "_deleetes" ?>"
												<?php if ($i > $numr) { echo 'style="display:none;"'; } ?>
												onclick="if(confirm('هل ترغب في حذف التقرير؟')) { window.location.href='custumreport.hnt?deletereport=<?= $row['id']; ?>&viewreport=<?= $viewreport; ?>'; }">
											<i class="bi bi-trash"></i> حذف
										</button>
									</div>
								</div>
								<?php
									$i++;
								} ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="text-center mt-4">
				<button type="submit" class="btn btn-lg btn-submit-report" onclick="warivaluetotxt(this.form.sel2,this.form.sqlfeilds);">
					<i class="bi bi-<?= $is_editing ? 'check-circle' : 'save' ?> me-2"></i>
					<?= $is_editing ? 'تحديث التقرير' : 'حفظ التقرير' ?>
				</button>
				<?php if ($is_editing) { ?>
				<a href="custumreport.hnt?viewreport=arady" class="btn btn-lg btn-outline-secondary ms-2">
					<i class="bi bi-x-circle me-2"></i>
					إلغاء
				</a>
				<?php } ?>
			</div>
		</form>
	</div>
</div>
