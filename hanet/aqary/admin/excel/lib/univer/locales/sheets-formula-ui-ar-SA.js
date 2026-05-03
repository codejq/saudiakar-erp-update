(function (global, factory) {
    if (typeof exports === 'object' && typeof module !== 'undefined') {
        module.exports = factory();
    } else if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else {
        global = typeof globalThis !== 'undefined' ? globalThis : global || self;
        global.UniverSheetsFormulaUiArSA = factory();
    }
})(this, function () {
    return {
        formula: {
            insert: {
                tooltip: 'الدوال',
                common: 'الدوال الشائعة'
            },
            functionType: {
                financial: 'المالية',
                date: 'التاريخ والوقت',
                math: 'الرياضيات والمثلثات',
                statistical: 'الإحصائية',
                lookup: 'البحث والمرجع',
                database: 'قاعدة البيانات',
                text: 'النص',
                logical: 'المنطقية',
                information: 'المعلومات',
                engineering: 'الهندسية',
                cube: 'المكعب',
                compatibility: 'التوافق',
                web: 'الويب',
                array: 'المصفوفات',
                univer: 'Univer',
                user: 'مخصصة من المستخدم',
                definedname: 'الأسماء المعرّفة'
            },
            moreFunctions: {
                confirm: 'تأكيد',
                prev: 'السابق',
                next: 'التالي',
                searchFunctionPlaceholder: 'ابحث عن دالة',
                allFunctions: 'كل الدوال',
                syntax: 'الصياغة'
            },
            operation: {
                copyFormulaOnly: 'نسخ الصيغة فقط',
                pasteFormula: 'لصق الصيغة'
            }
        }
    };
});
