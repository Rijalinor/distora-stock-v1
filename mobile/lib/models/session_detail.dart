class SessionDetail {
  const SessionDetail({
    required this.id,
    required this.principalName,
    required this.status,
    required this.sessionDate,
    required this.totalItems,
    required this.checkedItems,
    required this.matchedItems,
    required this.mismatchedItems,
    required this.assignedOfficer,
    required this.items,
  });

  final int id;
  final String principalName;
  final String status;
  final String? sessionDate;
  final int totalItems;
  final int checkedItems;
  final int matchedItems;
  final int mismatchedItems;
  final String? assignedOfficer;
  final List<SessionItem> items;

  factory SessionDetail.fromJson(Map<String, dynamic> json) {
    final rawItems = (json['items'] as List).cast<Map<String, dynamic>>();

    return SessionDetail(
      id: json['id'] as int,
      principalName: (json['principal'] as Map<String, dynamic>)['nama'] as String,
      status: json['status'] as String,
      sessionDate: json['session_date'] as String?,
      totalItems: json['total_items'] as int,
      checkedItems: json['checked_items'] as int,
      matchedItems: json['matched_items'] as int,
      mismatchedItems: json['mismatched_items'] as int,
      assignedOfficer: json['assigned_officer'] as String?,
      items: rawItems.map(SessionItem.fromJson).toList(),
    );
  }
}

class SessionItem {
  const SessionItem({
    required this.id,
    required this.kodeBarang,
    required this.namaBarang,
    required this.qtySistemDisplay,
    required this.qtyAktualDisplay,
    required this.selisih,
    required this.status,
    required this.checkedBy,
    required this.checkedAt,
  });

  final int id;
  final String kodeBarang;
  final String namaBarang;
  final String qtySistemDisplay;
  final String qtyAktualDisplay;
  final int selisih;
  final String status;
  final String? checkedBy;
  final String? checkedAt;

  factory SessionItem.fromJson(Map<String, dynamic> json) {
    return SessionItem(
      id: json['id'] as int,
      kodeBarang: json['kode_barang'] as String,
      namaBarang: json['nama_barang'] as String,
      qtySistemDisplay: json['qty_sistem_display'] as String,
      qtyAktualDisplay: json['qty_aktual_display'] as String,
      selisih: json['selisih'] as int,
      status: json['status'] as String,
      checkedBy: json['checked_by'] as String?,
      checkedAt: json['checked_at'] as String?,
    );
  }
}

