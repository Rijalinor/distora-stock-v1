import 'package:flutter/material.dart';

import 'screens/login_screen.dart';

class DistoraStockApp extends StatelessWidget {
  const DistoraStockApp({super.key});

  @override
  Widget build(BuildContext context) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFFFFB000),
      brightness: Brightness.dark,
      surface: const Color(0xFF171717),
    );

    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Distora Stock',
      theme: ThemeData(
        colorScheme: colorScheme,
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFF0F0F0F),
        visualDensity: VisualDensity.standard,
        textTheme: Typography.material2021().white.apply(
              bodyColor: Colors.white,
              displayColor: Colors.white,
              fontSizeFactor: 1.08,
            ),
        appBarTheme: const AppBarTheme(
          centerTitle: false,
          toolbarHeight: 64,
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            minimumSize: const Size.fromHeight(58),
            textStyle: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            minimumSize: const Size.fromHeight(58),
            textStyle: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
          ),
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: const Color(0xFF1B1B1B),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: Color(0xFF343434)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: Color(0xFF343434)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: Color(0xFFFFB000), width: 2),
          ),
          contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
          labelStyle: const TextStyle(fontSize: 18),
          hintStyle: const TextStyle(fontSize: 17),
        ),
        cardTheme: CardThemeData(
          color: const Color(0xFF1A1A1A),
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
            side: const BorderSide(color: Color(0xFF2B2B2B)),
          ),
        ),
      ),
      home: const LoginScreen(),
    );
  }
}
