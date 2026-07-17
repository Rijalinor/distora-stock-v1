class SessionSummary {
  const SessionSummary({
    required this.id,
    required this.principalName,
    required this.status,
    required this.totalItems,
    required this.checkedItems,
    required this.matchedItems,
    required this.mismatchedItems,
  });

  final int id;
  final String principalName;
  final String status;
  final int totalItems;
  final int checkedItems;
  final int matchedItems;
  final int mismatchedItems;

  factory SessionSummary.fromJson(Map<String, dynamic> json) {
    return SessionSummary(
      id: json['id'] as int,
      principalName: (json['principal'] as Map<String, dynamic>)['nama'] as String,
      status: json['status'] as String,
      totalItems: json['total_items'] as int,
      checkedItems: json['checked_items'] as int,
      matchedItems: json['matched_items'] as int,
      mismatchedItems: json['mismatched_items'] as int,
    );
  }
}
