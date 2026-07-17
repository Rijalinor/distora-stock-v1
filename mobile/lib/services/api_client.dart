import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config.dart';
import '../models/mobile_user.dart';
import '../models/session_detail.dart';
import '../models/session_summary.dart';

class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;
  String? token;

  Map<String, String> _headers({bool json = true}) => {
        if (json) 'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<(String, MobileUser)> login({
    required String email,
    required String password,
  }) async {
    final response = await _client.post(
      Uri.parse('$apiBaseUrl/login'),
      headers: _headers(),
      body: jsonEncode({'email': email, 'password': password}),
    );

    if (response.statusCode != 200) {
      throw Exception('Login gagal');
    }

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    final user = MobileUser.fromJson(data['user'] as Map<String, dynamic>);
    final newToken = data['token'] as String;
    token = newToken;
    return (newToken, user);
  }

  Future<List<SessionSummary>> fetchSessions() async {
    final response = await _client.get(
      Uri.parse('$apiBaseUrl/sessions'),
      headers: _headers(json: false),
    );

    if (response.statusCode != 200) {
      throw Exception('Gagal mengambil sesi');
    }

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    final items = (data['data'] as List).cast<Map<String, dynamic>>();
    return items.map(SessionSummary.fromJson).toList();
  }

  Future<SessionDetail> fetchSessionDetail(int sessionId) async {
    final response = await _client.get(
      Uri.parse('$apiBaseUrl/sessions/$sessionId'),
      headers: _headers(json: false),
    );

    if (response.statusCode != 200) {
      throw Exception('Gagal mengambil detail sesi');
    }

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    return SessionDetail.fromJson(data);
  }

  Future<SessionDetail> scanBarcode({
    required int sessionId,
    required String barcode,
    List<int>? qtyLevels,
    String mode = 'record',
    String? reason,
  }) async {
    final response = await _client.post(
      Uri.parse('$apiBaseUrl/sessions/$sessionId/scan'),
      headers: _headers(),
      body: jsonEncode({
        'barcode': barcode,
        if (qtyLevels != null && qtyLevels.isNotEmpty) 'qty_levels': qtyLevels,
        'mode': mode,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      }),
    );

    if (response.statusCode != 200) {
      final body = response.body;
      throw Exception(body.isEmpty ? 'Scan gagal' : body);
    }

    return fetchSessionDetail(sessionId);
  }
}
