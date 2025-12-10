# KSeF Integracja dla PrestaShop

Darmowy moduł integrujący sklep PrestaShop z Krajowym Systemem e-Faktur (KSeF).

## Co robi ten moduł?

Moduł umożliwia automatyczne lub ręczne przesyłanie faktur wystawionych w sklepie PrestaShop bezpośrednio do systemu KSeF. Dzięki temu spełnisz obowiązek fakturowania elektronicznego w Polsce.

## Główne funkcje

*   **Darmowy i Open Source:** Moduł jest dostępny całkowicie za darmo.
*   **Zgodność z KSeF:** Obsługuje aktualną strukturę logiczną e-Faktury FA(2).
*   **Tryby pracy:** Możliwość przełączania między środowiskiem testowym (Demo) a produkcyjnym.
*   **Automatyzacja:** Opcja automatycznej wysyłki faktur po ich wygenerowaniu w sklepie.
*   **Obsługa B2C:** Możliwość włączenia lub wyłączenia wysyłki faktur dla klientów indywidualnych.
*   **Logowanie:** Historia wysyłek i statusów faktur w bazie danych.

## Jak to działa?

1.  Moduł generuje plik XML zgodny ze schematem KSeF na podstawie danych zamówienia i faktury w PrestaShop.
2.  Łączy się z API KSeF, autoryzując się za pomocą tokenu.
3.  Wysyła fakturę i odbiera numer KSeF oraz UPO (Urzędowe Poświadczenie Odbioru).

## Wymagania

*   PrestaShop 1.7 lub nowsza.
*   Aktywny token autoryzacyjny KSeF (wygenerowany w Aplikacji Podatnika KSeF).

## Instalacja i Konfiguracja

1.  Zainstaluj moduł w panelu administracyjnym PrestaShop.
2.  Przejdź do konfiguracji modułu.
3.  Wprowadź swój NIP oraz Token autoryzacyjny.
4.  Wybierz tryb (Testowy/Produkcyjny).
5.  Skonfiguruj mapowanie stawek VAT (jeśli wymagane).

## Zachęcamy do testowania!

To jest wersja rozwojowa modułu. Zachęcamy do instalacji na środowiskach testowych, sprawdzania działania i zgłaszania wszelkich uwag oraz błędów. Twój feedback pomoże nam ulepszyć to narzędzie dla całej społeczności PrestaShop!

---
*Uwaga: Pamiętaj, aby przed użyciem na sklepie produkcyjnym dokładnie przetestować działanie modułu w środowisku testowym KSeF.*
