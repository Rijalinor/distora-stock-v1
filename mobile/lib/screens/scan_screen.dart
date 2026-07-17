import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../models/session_detail.dart';
import '../models/session_summary.dart';
import '../services/api_client.dart';

class ScanScreen extends StatefulWidget {
  const ScanScreen({super.key, required this.api, required this.session});

  final ApiClient api;
  final SessionSummary session;

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> {
  late Future<SessionDetail> _future;
  final _barcodeController = TextEditingController();
  final _qtyController = TextEditingController();
  final _mobileScannerController = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );

  bool _loading = false;
  bool _cameraOpen = false;
  String? _message;
  SessionDetail? _detail;

  @override
  void initState() {
    super.initState();
    _future = widget.api.fetchSessionDetail(widget.session.id);
  }

  @override
  void dispose() {
    _mobileScannerController.dispose();
    _barcodeController.dispose();
    _qtyController.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    setState(() {
      _future = widget.api.fetchSessionDetail(widget.session.id);
    });
  }

  List<int>? _parseQtyLevels() {
    final raw = _qtyController.text.trim();
    if (raw.isEmpty) {
      return null;
    }

    final values = raw
        .split(RegExp(r'[\s,.-]+'))
        .where((part) => part.isNotEmpty)
        .map(int.tryParse)
        .toList();

    if (values.any((value) => value == null)) {
      throw const FormatException('Qty harus angka, pisahkan dengan spasi atau koma.');
    }

    return values.cast<int>();
  }

  Future<void> _submitScan({String mode = 'record'}) async {
    final barcode = _barcodeController.text.trim();
    if (barcode.isEmpty) {
      setState(() {
        _message = 'Barcode belum diisi.';
      });
      return;
    }

    setState(() {
      _loading = true;
      _message = null;
    });

    try {
      final qtyLevels = _parseQtyLevels();
      final detail = await widget.api.scanBarcode(
        sessionId: widget.session.id,
        barcode: barcode,
        qtyLevels: qtyLevels,
        mode: mode,
      );

      if (!mounted) return;
      setState(() {
        _detail = detail;
        _message = 'Scan tersimpan.';
        _barcodeController.clear();
        if (mode == 'record') {
          _qtyController.clear();
        }
      });
      await _refresh();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _message = e.toString().replaceFirst('Exception: ', '');
      });
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  Future<void> _openCameraScanner() async {
    setState(() {
      _cameraOpen = true;
      _message = null;
    });

    String? scannedBarcode;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF0F0F0F),
      builder: (sheetContext) {
        return SafeArea(
          child: SizedBox(
            height: MediaQuery.of(sheetContext).size.height * 0.85,
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Scan Kamera',
                          style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.of(sheetContext).pop(),
                        icon: const Icon(Icons.close),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(20),
                    child: MobileScanner(
                      controller: _mobileScannerController,
                      fit: BoxFit.cover,
                      onDetect: (capture) {
                        final barcode = capture.barcodes.isNotEmpty
                            ? capture.barcodes.first.rawValue
                            : null;
                        if (barcode == null || barcode.isEmpty) {
                          return;
                        }

                        scannedBarcode = barcode;
                        Navigator.of(sheetContext).pop();
                      },
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 20),
                  child: Text(
                    'Arahkan kamera ke barcode. Setelah terbaca, hasil akan otomatis dipakai.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 16),
                  ),
                ),
                const SizedBox(height: 20),
              ],
            ),
          ),
        );
      },
    );

    await _mobileScannerController.stop();

    if (!mounted) {
      return;
    }

    setState(() {
      _cameraOpen = false;
    });

    if (scannedBarcode != null) {
      _barcodeController.text = scannedBarcode!;
      await _submitScan();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.session.principalName),
      ),
      body: FutureBuilder<SessionDetail>(
        future: _future,
        builder: (context, snapshot) {
          final detail = snapshot.data ?? _detail;

          if (snapshot.connectionState != ConnectionState.done && detail == null) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError && detail == null) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Text(snapshot.error.toString(), textAlign: TextAlign.center),
              ),
            );
          }

          final current = detail!;

          return SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _HeaderCard(detail: current),
                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text(
                          'Scan Barcode',
                          style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Pakai kamera atau isi manual jika perlu.',
                          style: TextStyle(
                            fontSize: 16,
                            color: Colors.white.withValues(alpha: 0.72),
                          ),
                        ),
                        const SizedBox(height: 18),
                        TextField(
                          controller: _barcodeController,
                          autofocus: true,
                          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                          decoration: const InputDecoration(
                            labelText: 'Barcode',
                            hintText: 'Ketik atau scan barcode...',
                          ),
                          onSubmitted: (_) => _submitScan(),
                        ),
                        const SizedBox(height: 14),
                        TextField(
                          controller: _qtyController,
                          keyboardType: TextInputType.number,
                          style: const TextStyle(fontSize: 18),
                          decoration: const InputDecoration(
                            labelText: 'Qty level manual',
                            hintText: 'Contoh: 1 2 0 atau 12,6',
                          ),
                        ),
                        const SizedBox(height: 14),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton.icon(
                            onPressed: _loading || _cameraOpen ? null : _openCameraScanner,
                            icon: const Icon(Icons.qr_code_scanner),
                            label: Text(_cameraOpen ? 'Kamera dibuka...' : 'Buka Kamera'),
                          ),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: FilledButton(
                                onPressed: _loading ? null : _submitScan,
                                child: Text(_loading ? 'Menyimpan...' : 'Simpan Scan'),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: OutlinedButton(
                                onPressed: _loading
                                    ? null
                                    : () {
                                        _qtyController.clear();
                                        _submitScan(mode: 'match');
                                      },
                                child: const Text('Tandai Cocok'),
                              ),
                            ),
                          ],
                        ),
                        if (_message != null) ...[
                          const SizedBox(height: 14),
                          DecoratedBox(
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.08),
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Text(
                                _message!,
                                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Item Terkini',
                          style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 12),
                        if (current.items.isEmpty)
                          const Text('Belum ada item yang discan.', style: TextStyle(fontSize: 16))
                        else
                          ...current.items.take(8).map(
                                (item) => Padding(
                                  padding: const EdgeInsets.only(bottom: 14),
                                  child: DecoratedBox(
                                    decoration: BoxDecoration(
                                      color: Colors.white.withValues(alpha: 0.05),
                                      borderRadius: BorderRadius.circular(16),
                                    ),
                                    child: Padding(
                                      padding: const EdgeInsets.all(14),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            item.namaBarang,
                                            style: const TextStyle(
                                              fontSize: 18,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            item.kodeBarang,
                                            style: TextStyle(
                                              fontSize: 15,
                                              color: Colors.white.withValues(alpha: 0.72),
                                            ),
                                          ),
                                          const SizedBox(height: 10),
                                          Wrap(
                                            spacing: 8,
                                            runSpacing: 8,
                                            children: [
                                              _InfoChip(label: 'Sistem ${item.qtySistemDisplay}'),
                                              _InfoChip(label: 'Aktual ${item.qtyAktualDisplay}'),
                                              _InfoChip(label: 'Selisih ${item.selisih}'),
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({required this.detail});

  final SessionDetail detail;

  @override
  Widget build(BuildContext context) {
    final progress = detail.totalItems == 0 ? 0.0 : detail.checkedItems / detail.totalItems;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              detail.principalName,
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            Text(
              '${detail.checkedItems}/${detail.totalItems} item • ${detail.matchedItems} cocok • ${detail.mismatchedItems} selisih',
              style: TextStyle(
                fontSize: 16,
                color: Colors.white.withValues(alpha: 0.78),
              ),
            ),
            const SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(value: progress, minHeight: 12),
            ),
            const SizedBox(height: 10),
            Text(
              'Progress ${(progress * 100).toStringAsFixed(0)}%',
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _InfoChip(label: 'Status ${detail.status}'),
                if (detail.assignedOfficer != null) _InfoChip(label: detail.assignedOfficer!),
                if (detail.sessionDate != null) _InfoChip(label: detail.sessionDate!),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        child: Text(
          label,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
        ),
      ),
    );
  }
}
