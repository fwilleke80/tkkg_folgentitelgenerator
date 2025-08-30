<?php
declare(strict_types=1);

/// @brief TKKG-Titelgenerator: Ein-Datei-App mit HTML-UI und URL-Parametern.
/// @details Parameter (GET):
/// @param[in] count Anzahl der Titel (Standard 10)
/// @param[in] seed  Optionaler Seed für deterministische Ergebnisse (Integer)
/// @param[in] json  Pfad zu externer JSON-Datei (Standard: data.json)
/// @param[in] search Optional: Suchwort, bevorzugt passende Phrasen
/// @param[in] format Ausgabeformat: html (Standard) | json | text
/// @param[in] template Optional: bestimmtes Template verwenden (Name, siehe JSON)
///
/// @example Beispiele:
///   /index.php?count=12
///   /index.php?count=5&seed=123&search=Moor
///   /index.php?format=json&count=3
///   /index.php?template=PLURAL_SUBJ__VERB_PHRASE&count=5

final class TitleGenerator
{
	/** @var array<string,int> */
	private array $_weights = [];

	/** @var array<string,array<int,string>> */
	private array $_chunks = [];

	/** @var array<int,string> */
	private array $_templates = [];

	/** @var bool */
	private bool $_useMtRand = false;

	/** @var string */
	private string $_search = '';

	/// @brief Konstruktor.
	public function __construct(array $weights, array $chunks, array $templates, ?int $seed = null, string $search = '')
	{
		$this->_weights = $weights;
		$this->_chunks = $chunks;
		$this->_templates = $templates;
		$this->_search = trim($search);

		if ($seed !== null)
		{
			mt_srand($seed);
			$this->_useMtRand = true;
		}
	}

	/// @brief Erzeugt N Titel.
	/// @param[in] n Anzahl
	/// @param[in] forceTemplate Optional: bestimmtes Template erzwingen
	/// @return array<int,string>
	public function GenerateMany(int $n, ?string $forceTemplate = null): array
	{
		$out = [];
		for ($i = 0; $i < $n; ++$i)
		{
			$out[] = $this->Generate($forceTemplate);
		}
		return $out;
	}

	/// @brief Erzeugt einen Titel.
	/// @return string
	public function Generate(?string $forceTemplate = null): string
	{
		$template = $forceTemplate ?? $this->PickWeighted($this->_weights);

		switch ($template)
		{
			case 'ART_ADJ_NOUN__PP_ORT':
			{
				$a = $this->Pick('ART_ADJ_NOUN_NOM');
				$b = $this->Pick('PP_ORT');
				return "$a $b";
			}
			case 'ART_NOUN__GEN_ATTR':
			{
				$a = $this->Pick('ART_NOUN_NOM');
				$b = $this->Pick('GEN_ATTR');
				return "$a $b";
			}
			case 'PLURAL_SUBJ__VERB_PHRASE':
			{
				$s = $this->Pick('PLURAL_SUBJ');
				$v = $this->Pick('VERB_PHRASE');
				return "$s $v";
			}
			case 'ABSTRAKT__PP_ORT':
			{
				$a = $this->Pick('ABSTRAKT');
				$o = $this->Pick('PP_ORT');
				return "$a $o";
			}
			case 'PP_IM__ORT__GEN_ATTR':
			{
				$o = $this->Pick('PP_ORT');
				$g = $this->Pick('GEN_ATTR');
				return "$o $g";
			}
			case 'ART_NOUN__PP_AUS':
			{
				$a = $this->Pick('ART_NOUN_NOM');
				$p = $this->Pick('PP_AUS');
				return "$a $p";
			}
			case 'THEMA__PP_MIT':
			{
				$t = $this->Pick('ART_NOUN_NOM');
				$p = $this->Pick('PP_MIT');
				return "$t $p";
			}
			case 'THEMA__PP_FUER':
			{
				$t = $this->Pick('ART_NOUN_NOM');
				$p = $this->Pick('PP_FUER');
				return "$t $p";
			}
			case 'THEMA__PP_NACH':
			{
				$t = $this->Pick('ART_NOUN_NOM');
				$p = $this->Pick('PP_NACH');
				return "$t $p";
			}
			case 'NAME_APPOSITION':
			{
				return $this->Pick('NAME_APPOSITION');
			}
			case 'ART_NOUN__RELCLAUSE':
			{
				$t = $this->Pick('ART_NOUN_NOM');
				$r = $this->Pick('RELCLAUSE');
				return "$t, $r";
			}
			case 'SUBJ_VERB_NEG':
			{
				$s = $this->Pick('SUBJ_NEG');
				$v = $this->Pick('VERB_NEG');
				return "$s $v";
			}
			case 'ZEIT__NEBENSATZ':
			{
				$z = $this->Pick('ZEIT');
				$n = $this->Pick('NEBENSATZ');
				return "$z, $n";
			}
			default:
			{
				return 'Unbenannter Fall';
			}
		}
	}

	/// @brief Zufall: ganzzahliges Intervall.
	private function RandInt(int $min, int $max): int
	{
		if ($this->_useMtRand)
		{
			return mt_rand($min, $max);
		}
		return random_int($min, $max);
	}

	/// @brief Gewichtet zufälliges Template wählen.
	private function PickWeighted(array $weights): string
	{
		$sum = array_sum($weights);
		if ($sum <= 0)
		{
			return $this->_templates[$this->RandInt(0, count($this->_templates) - 1)];
		}
		$r = $this->RandInt(1, $sum);
		$acc = 0;
		foreach ($weights as $key => $w)
		{
			$acc += $w;
			if ($r <= $acc)
			{
				return $key;
			}
		}
		return array_key_first($weights);
	}

	/// @brief Zufälligen Eintrag aus einem Chunk nehmen, mit optionaler Suchwort-Bevorzugung.
	private function Pick(string $key): string
	{
		$list = $this->_chunks[$key] ?? null;
		if (!$list || count($list) === 0)
		{
			return 'Fehlender Chunk';
		}

		// Optional: Suchwort bevorzugen (wenn vorhanden)
		if ($this->_search !== '')
		{
			$filtered = array_values(array_filter($list, function(string $s)
			{
				return mb_stripos($s, $this->_search) !== false;
			}));
			if (count($filtered) > 0)
			{
				$list = $filtered;
			}
		}

		return $list[$this->RandInt(0, count($list) - 1)];
	}
}

/// @brief Lädt die JSON-Datei oder benutzt Fallback.
function LoadDataset(string $jsonPath): array
{
	$fallback = __DIR__ . '/data.json';
	$path = $jsonPath !== '' ? $jsonPath : $fallback;

	if (!is_file($path))
	{
		// Minimaler Inline-Fallback, falls JSON fehlt.
		$inline = [
			'templates' => ['ART_ADJ_NOUN__PP_ORT','PLURAL_SUBJ__VERB_PHRASE'],
			'weights' => ['ART_ADJ_NOUN__PP_ORT'=>1,'PLURAL_SUBJ__VERB_PHRASE'=>1],
			'chunks' => [
				'ART_ADJ_NOUN_NOM' => ['Das leere Grab','Der falsche Priester'],
				'PP_ORT' => ['im Moor','im Burghotel'],
				'PLURAL_SUBJ' => ['Hundediebe','Schmuggler'],
				'VERB_PHRASE' => ['kennen keine Gnade','reisen unerkannt']
			]
		];
		return $inline;
	}

	$raw = file_get_contents($path);
	if ($raw === false)
	{
		throw new RuntimeException('JSON konnte nicht gelesen werden: ' . $path);
	}
	$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

	return $data;
}

/// @brief Ausgabe als HTML.
function RenderHtml(array $titles, array $params): void
{
	header('Content-Type: text/html; charset=utf-8');
	$permalink = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$count = (int)$params['count'];
	$seed = $params['seed'] !== null ? (int)$params['seed'] : '';
	$search = htmlspecialchars($params['search'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$template = htmlspecialchars($params['template'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$jsonPath = htmlspecialchars($params['json'] ?? 'tkkg_titles.json', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

	echo "<!DOCTYPE html>\n<html lang=\"de\">\n<head>\n<meta charset=\"utf-8\">\n<title>TKKG-Titelgenerator</title>\n";
	echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
	echo "<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu;line-height:1.4;padding:1rem;max-width:900px;margin:0 auto}h1{margin:0 0 .5rem 0}form{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.5rem;align-items:end}label{font-size:.9rem}input,select,button{padding:.4rem .5rem}ul{padding-left:1.25rem}code{background:#f3f3f3;padding:.15rem .3rem;border-radius:.25rem}footer{margin-top:1rem;color:#666;font-size:.9rem}</style>\n</head>\n<body>\n";
	echo "<h1>TKKG‑Titelgenerator</h1>\n";

	echo "<form method=\"get\">\n";
	echo "<div><label>Anzahl<br><input type=\"number\" name=\"count\" value=\"{$count}\" min=\"1\" max=\"200\"></label></div>\n";
	echo "<div><label>Seed<br><input type=\"number\" name=\"seed\" value=\"{$seed}\" placeholder=\"leer = zufällig\"></label></div>\n";
	echo "<div><label>Suchwort<br><input type=\"text\" name=\"search\" value=\"{$search}\" placeholder=\"bevorzugt passende Phrasen\"></label></div>\n";
	echo "<div><label>Template<br><input type=\"text\" name=\"template\" value=\"{$template}\" placeholder=\"optional\"></label></div>\n";
	echo "<div><label>JSON‑Datei<br><input type=\"text\" name=\"json\" value=\"{$jsonPath}\"></label></div>\n";
	echo "<div><button type=\"submit\">Generieren</button></div>\n";
	echo "</form>\n";

	echo "<p>Permalink: <a href=\"{$permalink}\"><code>{$permalink}</code></a></p>\n";

	echo "<ul>\n";
	foreach ($titles as $t)
	{
		$e = htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		echo "<li>{$e}</li>\n";
	}
	echo "</ul>\n";

	$baseUrl = strtok($_SERVER['REQUEST_URI'], '?') ?: '/index.php';
	$query = http_build_query(array_merge($params, ['format'=>'json']));
	$jsonUrl = htmlspecialchars($baseUrl . '?' . $query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

	$queryTxt = http_build_query(array_merge($params, ['format'=>'text']));
	$txtUrl = htmlspecialchars($baseUrl . '?' . $queryTxt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

	echo "<footer>\n<p>API: <a href=\"{$jsonUrl}\">JSON</a> | <a href=\"{$txtUrl}\">Text</a></p>\n";
	echo "<p>Templates sind in der JSON-Datei hinterlegt. Optional kann ein <code>template</code>-Name erzwungen werden.</p>\n</footer>\n";

	echo "</body></html>";
}

/// @brief Ausgabe als JSON.
function RenderJson(array $titles): void
{
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['titles'=>$titles], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/// @brief Ausgabe als Plaintext.
function RenderText(array $titles): void
{
	header('Content-Type: text/plain; charset=utf-8');
	echo implode(PHP_EOL, $titles);
}

// ======== Controller ========

$count = isset($_GET['count']) ? max(1, (int)$_GET['count']) : 10;
$seed = isset($_GET['seed']) && $_GET['seed'] !== '' ? (int)$_GET['seed'] : null;
$jsonPath = isset($_GET['json']) ? (string)$_GET['json'] : 'tkkg_titles.json';
$search = isset($_GET['search']) ? (string)$_GET['search'] : '';
$format = isset($_GET['format']) ? (string)$_GET['format'] : 'html';
$template = isset($_GET['template']) ? (string)$_GET['template'] : null;

try
{
	$data = LoadDataset($jsonPath);
	$gen = new TitleGenerator(
		$data['weights'] ?? [],
		$data['chunks'] ?? [],
		$data['templates'] ?? [],
		$seed,
		$search
	);
	$titles = $gen->GenerateMany($count, $template);

	switch ($format)
	{
		case 'json':
		{
			RenderJson($titles);
			break;
		}
		case 'text':
		{
			RenderText($titles);
			break;
		}
		default:
		{
			RenderHtml($titles, [
				'count'=>$count,
				'seed'=>$seed,
				'json'=>$jsonPath,
				'search'=>$search,
				'template'=>$template
			]);
			break;
		}
	}
}
catch (Throwable $e)
{
	header('Content-Type: text/plain; charset=utf-8', true, 500);
	echo 'Fehler: ' . $e->getMessage();
}
