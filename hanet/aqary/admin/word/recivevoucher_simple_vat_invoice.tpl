<style>
    .invoice-cell {
        width: 65mm;
        text-align: right;
        vxertical-align: text-top;
    }

    .invoice-label {
        border: 1px #3F51B5;
        padding: 5px;
        margin: 1px;
        border-radius: 5px;
        border-style: dotted;
    }

    .invoice-body {
        border: 1px #3F51B5;
        padding: 5px;
        margin: 1px;
        border-radius: 5px;
        border-style: solid;
    }

    .invoice-head {
        background: #eee3f1;
    }

    .invoice-foot {
        background: #eee3f1;
    }

    .invoice-items {
        border-style: dashed;
    }

    .invoice-items:before {
        content: "\00a0 ";;
    }
</style>

<table border="0"  ="" align="center" class="tablefont1818 bluefont"
       style="width:190mm; border-collapse:collapse ">
    <tbody>
    <tr>
        <td class="invoice-cell">
            <div class="invoice-label"> التاريخ:{_التاريخ_}
            </div>
        </td>
        <td class="invoice-cell">
            <div class="invoice-label center"> فاتورة ضريبية رقم: {_رقم_السند_}
            </div>
        </td>
        <td class="invoice-cell">
            <div class="invoice-label">تاريخ الاستحقاق :{تاريخ_الإستحقاق}
            </div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="invoice-label">
                اسم المورد:
                {اسم_البائع}
            </div>
        </td>
        <td>
            <div class="invoice-label">
                الرقم الضريبي للمورد:
                {الرقم_الضريبي_للبائع}
            </div>
        </td>
        <td>
            <div class="invoice-label">
                عنوان المورد:
                {عنوان_البائع}
            </div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="invoice-label">
                اسم العميل:
                {اسم_المستلم}
            </div>
        </td>
        <td>
            <div class="invoice-label">
                الرقم الضريبي للعميل:
                {رقم_العميل_الضريبي}
            </div>
        </td>
        <td>
            <div class="invoice-label"> عنوان العميل:
                {عنوان_العميل}
            </div>
        </td>
    </tr>


    </tbody>
</table>

<table border="0"  class="invoice-table" align="center" class="tablefont1818 bluefont"
       style="width:190mm; border-collapse:collapse ">
    <tbody>
    <tr>
        <td style="width:20mm">
            <div class="invoice-body invoice-head center">سند
                <div>
        </td>
        <td style="width:90mm">
            <div class="invoice-body invoice-head center"> البيان
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-head center"> المبلغ
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-head center"> الضريبة 15%
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-head center"> الإجمالي
                <div>
        </td>
    </tr>

    <tr>
        <td style="width:20mm;">
            <div class="invoice-body invoice-items">{كود_القسط_0}
                <div>
        </td>
        <td style="width:90mm">
            <div class="invoice-body invoice-items center">{_بيان_السند_0}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">{_المبلغ_0}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items ">{_ضريبة_القيمة_المضافة_}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">{_المبلغ_0_}
                <div>
        </td>
    </tr>
    <tr>
        <td style="width:20mm;">
            <div class="invoice-body invoice-items">&nbsp;
			{كود_القسط_1}
			<div>
        </td>
        <td style="width:90mm">
            <div class="invoice-body invoice-items center">{_بيان_السند_1}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_1}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items ">
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_1_}
                <div>
        </td>
    </tr>
    <tr>
        <td style="width:20mm;">
            <div class="invoice-body invoice-items">&nbsp;
			{كود_القسط_2}<div>
        </td>
        <td style="width:90mm">
            <div class="invoice-body invoice-items center">{_بيان_السند_2}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_2}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items ">
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_2_}
                <div>
        </td>
    </tr>
    <tr>
        <td style="width:20mm;">
            <div class="invoice-body invoice-items">&nbsp;
			{كود_القسط_3}
			<div>
        </td>
        <td style="width:90mm">
            <div class="invoice-body invoice-items center">{_بيان_السند_3}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_3}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items ">
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_3_}
                <div>
        </td>
    </tr>
    <tr>
        <td style="width:20mm;">
            <div class="invoice-body invoice-items">&nbsp;
			{كود_القسط_4}
			<div>
        </td>
        <td style="width:90mm">
            <div class="invoice-body invoice-items center">{_بيان_السند_4}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_4}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items ">
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_4_}
                <div>
        </td>
    </tr>
    <tr>
        <td style="width:20mm;">
            <div class="invoice-body invoice-items">&nbsp;
			{كود_القسط_5}
			<div>
        </td>
        <td style="width:90mm">
            <div class="invoice-body invoice-items center">{_بيان_السند_5}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_5}
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items ">
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
			{_المبلغ_5_}
                <div>
        </td>
    </tr>


    <tr>
        <td style="width:20mm;">
            <div class="invoice-body invoice-items">&nbsp;<div>
        </td>
        <td style="width:90mm">
            <div class="invoice-body invoice-items center">
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items ">
                <div>
        </td>
        <td>
            <div class="invoice-body invoice-items">
                <div>
        </td>
    </tr>
    <tr>
        <td style="width:20mm;">
            <div class="invoice-body   invoice-foot ">&nbsp;<div>
        </td>
        <td style="width:90mm" colspan=3>
            <div class="invoice-body   invoice-foot center"> الاجمالي شامل الضريبة
                <div>
        </td>

        <td>
            <div class="invoice-body   invoice-foot">{_اجمالي_المبلغ_}
                <div>
        </td>
    </tr>


    </tbody>
</table>

<table id="stalemtDataTable" border="1" 
style='display:{stalemtDataTable};margin-top:35px;border: 1px solid #000000;width:100%; border-collapse:collapse ;'>
				<tr>
				<th>الايجار</th>
				<th>ضريبة ايجار</th>
				<th>المياه</th>
				<th>العمولة</th>
				<th>ضريبة عمولة</th>
				<th>خدمات</th>
				<th> المطلوب</th>
				<th>المدفوع</th>
				<th>المتبقي</th>
				</tr>
				<tr>
				<td id='rentValue'>{rentValue}</td>
				<td id='rentVat'>{rentVat}</td>
				<td id='water'>{water}</td>
				<td id='comision'>{comision}</td>
				<td id='comisionVat'>{comisionVat}</td>
				<td id='services'>{services}</td>
				<td id='requiredamound'>{requiredamound}</td>
				<td id='paid'>{paid}</td>
				<td id='remaining' class='uifont16 bluetext '>{remaining}</td>
				</tr>				
				</table>
				
<br>
<table border="0"  ="" align="center" class="tablefont1818 bluefont"
       style="width:190mm,border:3px solid blue; border-collapse:collapse ">
    <tbody>
    <tr>
        <td style="width:40mm;text-align:right;vxertical-align: text-top;">مبلغ وقدرة
        </td>
        <td style="text-align:center;width:130mm;border-bottom:1px dashed #000000"> {المبلغ_حروف} فقط لا غير</td>
        <td style="direction: ltr; width: 40mm; text-align: left;">The Sum Of:</td>
    </tr>

    </tbody>
</table>


<table border="0"  ="" align="center" class="tablefont1818 bluefont"
       style="width:190mm,border:3px solid blue; border-collapse:collapse ">
    <tbody>
    <tr>
        <td style="width:40mm;text-align:right;vxertical-align: text-top;">ملحوظات</td>
        <td style="text-align:center;width:130mm;border-bottom:1px dashed #000000"> {_ملحوظات_} </td>
        <td style="width:40mm;text-align:left;vxertical-align: top;"> Notes</td>
    </tr>

    </tbody>
</table>
<br>
<table border="0"  ="" align="center" class="tablefont1818 bluefont"
       style="width:190mm,border:3px solid blue; border-collapse:collapse ">
    <tbody>
    <tr>
        <td style="width:105mm;text-align:center;vxertical-align: text-top;">الحسابات</td>
        <td style="width:105mm;text-align:center;vxertical-align: top;"> توقيع المستلم</td>
    </tr>
    <tr>
        <td style="width:105mm;text-align:center;vxertical-align: text-top;">{_الحسابات_}</td>
        <td style="width:105mm;text-align:center;vxertical-align: top;">
        </td>
    </tr>
    <tr>
        <td style="width:105mm;text-align:center;vxertical-align: text-top;"><br>__________________________
        </td>
        <td style="width:105mm;text-align:center;vxertical-align: top;"><br> __________________________</td>
    </tr>
    </tbody>
</table>

<br>
<img src="/aqary/admin/images/stampimage.png" class="stampimage " onerror="this.style.display='none';">
<img src="/aqary/admin/images/signaturerimage.png" class="signaturerimage " onerror="this.style.display='none';">

<DIV style="FLOAT: left" class=barcode>
     <div id="qrcode"></div>
</DIV>



<style>
    .signaturerimage {
        position: relative;
        bottom: 160px;
        right: 0mm;
        max-width: 35mm !important;
        max-height: 35mm !important;
        margin-bottom: -150px;
    }

    .stampimage {
        position: relative;
        bottom: 160px;
        right: 85mm;
        max-width: 35mm !important;
        max-height: 35mm !important;
        margin-bottom: -150px;
    }

    .qbarcodepng {
        position: relative;
        bottom: 190px;
        max-width: 90px;
        width: 90px;
        margin-bottom: -150px;
    }
</style>