import { z } from "zod";

export const SYSTEM_PROMPT = `Jesteś precyzyjnym, konstruktywnym recenzentem kodu dla projektu AI Card Collector.
Stack projektu: PHP 8.x, MySQL/MariaDB, vanilla JS, brak frameworka.

Oceniasz diff w pięciu kryteriach (skala 1-10):
- implementationCorrectness: Poprawność: czy kod robi to, co deklaruje
- idiomaticity: Idiomatyczność: zgodność z PHP 8.x, PSR-4, konwencjami repo
- complexity: Złożoność: prostota rozwiązania względem problemu
- testRiskCoverage: Pokrycie testami proporcjonalne do ryzyka (1=brak testów dla krytycznej zmiany)
- securitySafety: Bezpieczeństwo: PDO prepared statements, CSRF, htmlspecialchars, brak eval/exec

Następnie wydaj wiążący werdykt (pass/fail) i dołącz podsumowanie 2-3 zdania po polsku.

Reguły projektu:
- Każdy plik PHP musi zaczynać się od <?php declare(strict_types=1);
- Każde zapytanie SQL: PDO prepared statements (zero string concatenation)
- Każda wartość w HTML: htmlspecialchars($v, ENT_QUOTES, 'UTF-8')
- Każdy POST form: walidacja CSRF tokenu
- Brak eval(), exec(), system(), shell_exec(), passthru()`;

export const REVIEW_SCHEMA = z.object({
  implementationCorrectness: z.number().describe(
    "Poprawność implementacji (1-10). 1=logika błędna lub psuje obecne zachowanie. 10=poprawna na ścieżce głównej i w edge cases."
  ),
  idiomaticity: z.number().describe(
    "Idiomatyczność PHP 8.x (1-10). 1=ignoruje konwencje repo. 10=wzorcowy PHP 8 z declare(strict_types=1)."
  ),
  complexity: z.number().describe(
    "Złożoność (1-10). 1=nadmiernie skomplikowane. 10=najprostsze możliwe rozwiązanie."
  ),
  testRiskCoverage: z.number().describe(
    "Pokrycie testami (1-10). 1=krytyczna zmiana bez testów. 10=wszystkie ścieżki ryzyka pokryte."
  ),
  securitySafety: z.number().describe(
    "Bezpieczeństwo (1-10). 1=SQL injection/XSS/brak CSRF. 10=wszystkie reguły security spełnione."
  ),
  verdict: z.enum(["pass", "fail"]).describe(
    "Werdykt: pass=zmiana bezpieczna do merge, fail=wymaga poprawek"
  ),
  summary: z.string().describe(
    "Podsumowanie 2-3 zdania po polsku, gotowe jako komentarz do PR-a. Konkretne wskazówki dla autora."
  ),
});

export type Review = z.infer<typeof REVIEW_SCHEMA>;
