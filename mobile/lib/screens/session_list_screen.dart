import 'package:flutter/material.dart';

import '../models/session_summary.dart';
import '../services/api_client.dart';
import 'scan_screen.dart';

class SessionListScreen extends StatefulWidget {
  const SessionListScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<SessionListScreen> createState() => _SessionListScreenState();
}

class _SessionListScreenState extends State<SessionListScreen> {
  late Future<List<SessionSummary>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.api.fetchSessions();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Session Hari Ini'),
      ),
      body: FutureBuilder<List<SessionSummary>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }

          final sessions = snapshot.data ?? [];
          if (sessions.isEmpty) {
            return const Center(
              child: Text(
                'Tidak ada session hari ini',
                style: TextStyle(fontSize: 20),
              ),
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(20),
            itemCount: sessions.length,
            separatorBuilder: (_, __) => const SizedBox(height: 14),
            itemBuilder: (context, index) {
              final session = sessions[index];
              final progress = session.totalItems == 0 ? 0.0 : session.checkedItems / session.totalItems;

              return InkWell(
                borderRadius: BorderRadius.circular(20),
                onTap: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ScanScreen(api: widget.api, session: session),
                    ),
                  );
                },
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          session.principalName,
                          style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '${session.checkedItems}/${session.totalItems} item diperiksa',
                          style: TextStyle(
                            fontSize: 16,
                            color: Colors.white.withValues(alpha: 0.72),
                          ),
                        ),
                        const SizedBox(height: 14),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(999),
                          child: LinearProgressIndicator(value: progress, minHeight: 12),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            _StatChip(label: 'Cocok ${session.matchedItems}'),
                            const SizedBox(width: 8),
                            _StatChip(label: 'Selisih ${session.mismatchedItems}'),
                            const Spacer(),
                            const Icon(Icons.chevron_right, size: 28),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip({required this.label});

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
