# TKKG Folgentitel Generator

Ein kleiner **PHP-Titelgenerator** für TKKG-Folgentitel.  
Er kombiniert einfache Templates mit Asset-Listen, um zufällige neue Episodentitel zu erzeugen.  
Die Generierung ist **rekursiv** und erlaubt auch **optionale Platzhalter**.

---

## Features

- **Templates mit Platzhaltern** in eckigen Klammern, z. B.  
  ```text
  [art_adj_noun] [pp_im_ort]
  [plural_subj] [verb_phrase]
  ```
- **Rekursive Expansion**: Assets können wiederum Platzhalter enthalten.
- **Optionale Platzhalter** mit Wahrscheinlichkeit:  
  - `[token?]` → 50 % Wahrscheinlichkeit, dass der Platzhalter entfällt  
  - `[token?NN]` → NN % Wahrscheinlichkeit (0–100), dass der Platzhalter eingefügt wird  
- **Whitespace-Korrektur**: doppelte Leerzeichen werden reduziert, überflüssiger Leerraum vor Satzzeichen entfernt.
- **Minimalistisch**: nur ein Parameter `count` via URL, keine Seeds, keine Gewichtungen.

---

## Setup

1. Lege die Dateien in einen Ordner, z. B. auf deinem Webserver:
   ```
   index.php
   tkkg_data.json
   ```
2. Stelle sicher, dass dein Server PHP 7.4+ oder neuer verwendet.

---

## Nutzung

Rufe den Generator im Browser auf:

```url
http://localhost/index.php
```

Standardmäßig werden **10 Titel** erzeugt.  

Mit Parameter `count` kannst du die Anzahl festlegen:

```url
http://localhost/index.php?count=5
```

Beispielausgaben:

```
Das dunkle Grab im Moor
Crash-Kids machen Überstunden
Die Rache des Hexenmeisters
Paul läuft schnell
Paul läuft
```

---

## Datenstruktur

Die Datei `tkkg_data.json` enthält:

- **templates**: Liste von Template-Strings mit Platzhaltern.
- **assets**: Wörterbuch, Schlüssel = Platzhaltername, Wert = Liste von Strings.

Beispiel:

```json
{
  "templates": [
    "[art_adj_noun] [pp_im_ort]",
    "[plural_subj] [verb_phrase]"
  ],
  "assets": {
    "art_adj_noun": [
      "Das [adj] Grab",
      "Der [adj] Priester"
    ],
    "pp_im_ort": [
      "im Moor",
      "im Burghotel"
    ],
    "plural_subj": [
      "Hundediebe",
      "Schmuggler"
    ],
    "verb_phrase": [
      "kennen keine Gnade",
      "reisen unerkannt"
    ],
    "adj": [
      "dunkle",
      "falsche",
      "leere
    ]
  }
}
```

---

## Funktionsweise

1. `Pick()` wählt zufällige Elemente aus einer Liste.
2. `Expand()` ersetzt Platzhalter rekursiv durch Inhalte aus den Assets.  
   - Optional-Syntax `[token?]` / `[token?NN]` wird berücksichtigt.
3. `NormalizeTitle()` entfernt doppelte Leerzeichen und korrigiert Leerraum.
4. `GenerateTitle()` wählt ein Template, expandiert es und normalisiert den Titel.
5. `Main()` lädt die JSON-Datei, liest den `count`-Parameter und gibt die Titel aus.

---

## Beispiel-Ablauf

Template:  
```
"[person] [verb] [adverb?]"
```

Assets:  
```json
"person": ["Paul", "Anna"],
"verb": ["läuft", "rennt"],
"adverb": ["schnell", "hastig"]
```

Mögliche Ergebnisse:  
- `Paul läuft`  
- `Anna rennt schnell`  
- `Paul rennt hastig`  

---

## Lizenz

This is free and unencumbered software released into the public domain.
Anyone is free to copy, modify, publish, use, compile, sell, or distribute this software, either in source code form or as a compiled binary, for any purpose, commercial or non-commercial, and by any means.

In jurisdictions that recognize copyright laws, the author or authors of this software dedicate any and all copyright interest in the software to the public domain. We make this dedication for the benefit of the public at large and to the detriment of our heirs and successors. We intend this dedication to be an overt act of relinquishment in perpetuity of all present and future rights to this software under copyright law.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

For more information, please refer to https://unlicense.org

© 2025 Frank Willeke  
https://www.frankwilleke.de  
https://github.com/fwilleke80/tkkg_folgentitelgenerator