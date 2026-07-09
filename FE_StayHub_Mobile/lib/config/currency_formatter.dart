import 'package:flutter/services.dart';

class CurrencyInputFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    if (newValue.selection.baseOffset == 0) {
      return newValue;
    }

    String cleanText = newValue.text.replaceAll(RegExp(r'\D'), '');
    if (cleanText.isEmpty) {
      return newValue.copyWith(text: '', selection: const TextSelection.collapsed(offset: 0));
    }

    final reg = RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))');
    String newText = cleanText.replaceAllMapped(reg, (Match m) => '${m[1]}.');

    return newValue.copyWith(
      text: newText,
      selection: TextSelection.collapsed(offset: newText.length),
    );
  }
}

String formatMoney(num value) {
  final str = value.toStringAsFixed(0);
  final reg = RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))');
  return '${str.replaceAllMapped(reg, (Match m) => '${m[1]}.')}đ';
}

double parseMoney(String value) {
  return double.tryParse(value.replaceAll('.', '')) ?? 0.0;
}

String formatMoneyInput(String value) {
  String cleanText = value.replaceAll(RegExp(r'\D'), '');
  if (cleanText.isEmpty) return '';
  final reg = RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))');
  return cleanText.replaceAllMapped(reg, (Match m) => '${m[1]}.');
}
