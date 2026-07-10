import 'package:fe_stayhub_mobile/models/contract.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('parses room service prices with source metadata from contract API', () {
    final contract = Contract.fromJson({
      'id': 1,
      'contract_code': 'HD-2026-0001',
      'room_id': 10,
      'room_number': 'A101',
      'representative_tenant_id': 5,
      'tenant_name': 'Tenant Test',
      'start_date': '2026-01-01',
      'room_price': '4500000.00',
      'deposit_amount': '9000000.00',
      'status': Contract.STATUS_ACTIVE,
      'is_deposit_paid': true,
      'room_services': [
        {
          'room_service_id': 99,
          'id': 3,
          'name': 'Internet tốc độ cao khu A',
          'slug': 'internet',
          'charge_method': 3,
          'charge_method_label': 'Theo phòng',
          'unit_name': 'phòng',
          'price': '150000.00',
          'price_source': 'contract',
          'price_source_label': 'Giá theo hợp đồng',
          'is_required': true,
        },
      ],
    });

    expect(contract.roomServices, hasLength(1));
    expect(contract.roomServices!.first, isA<ContractRoomService>());
    expect(contract.roomServices!.first.name, 'Internet tốc độ cao khu A');
    expect(contract.roomServices!.first.price, 150000);
    expect(contract.roomServices!.first.priceSource, 'contract');
    expect(contract.roomServices!.first.priceSourceLabel, 'Giá theo hợp đồng');
  });
}
