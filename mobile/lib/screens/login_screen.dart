import 'package:flutter/material.dart';

import '../services/api_client.dart';
import 'session_list_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController(text: 'officer1@distora.com');
  final _passwordController = TextEditingController(text: 'password');
  final _api = ApiClient();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await _api.login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );

      if (!mounted) return;

      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => SessionListScreen(api: _api)),
      );
    } catch (e) {
      setState(() {
        _error = e.toString();
      });
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Distora Stock Mobile'),
      ),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Masuk',
                      style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Pakai akun officer yang sudah disiapkan.',
                      style: TextStyle(
                        fontSize: 16,
                        color: Colors.white.withValues(alpha: 0.72),
                      ),
                    ),
                    const SizedBox(height: 24),
                    TextField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      style: const TextStyle(fontSize: 18),
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        hintText: 'officer1@distora.com',
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _passwordController,
                      obscureText: true,
                      style: const TextStyle(fontSize: 18),
                      decoration: const InputDecoration(
                        labelText: 'Password',
                        hintText: 'password',
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          _error!,
                          style: const TextStyle(color: Colors.redAccent, fontSize: 15),
                        ),
                      ),
                    FilledButton(
                      onPressed: _loading ? null : _submit,
                      child: Text(_loading ? 'Masuk...' : 'Login'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
