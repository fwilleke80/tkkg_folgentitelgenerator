<?php
declare(strict_types=1);

ini_set('display_errors', '0');  // don’t leak stack traces
ini_set('log_errors', '1');
@set_time_limit(2);                    // short runtime
@ini_set('memory_limit', '64M'); // small memory cap is fine for this

header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');


/// @brief   TKKG Folgentitel-Generator
/// @details Loads data.json from the same folder and renders a form to generate TKKG episode titles.
/// @author  Frank Willeke


// --------------------------------------------------------------------------------------
// Config / Metadata
// --------------------------------------------------------------------------------------

/** @var string */
const SCRIPTTITLE = 'TKKG Folgentitel-Generator';
/** @var string */
const SCRIPTVERSION = '1.0.1';
/** @var string */
const DATAFILENAME = 'data.json';

/// @brief Liest und decodiert JSON-Datei
function LoadData(string $path): array
{
	$raw = file_get_contents($path);
	if ($raw === false)
		return [];
	return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}

/// @brief Zufallselement aus einer Liste
function Pick(array $list): string
{
	if (count($list) === 0)
	{
		return '';
	}
	return $list[array_rand($list)];
}

/// @brief
function RemoveRedundantSpaces(string $s)
{
	return preg_replace('/ {2,}/', ' ', $s);
}

/// @brief Expandiert rekursiv Platzhalter [token], inkl. optionaler Tokens [name?] / [name?NN].
function Expand(string $s, array $assets, int $depth = 8): string
{
	if ($depth <= 0)
	{
		return $s;
	}

	$pattern = '/\[(?<key>[a-zA-Z0-9_]+)(?<opt>\?(?<pct>\d{1,3})?)?\]/u';

	return preg_replace_callback($pattern, function(array $m) use ($assets, $depth)
	{
		$key = $m['key'] ?? '';
		$isOpt = isset($m['opt']) && $m['opt'] !== '';
		$pct = isset($m['pct']) && $m['pct'] !== '' ? (int)$m['pct'] : 50; // default 50%

		// Optionaler Platzhalter: ggf. leer ersetzen
		if ($isOpt)
		{
			$roll = mt_rand(1, 100); // 1..100
			if ($roll > max(0, min(100, $pct)))
			{
				return ''; // fällt weg
			}
		}

		// Normale Expansion
		if ($key === '' || !array_key_exists($key, $assets) || empty($assets[$key]))
		{
			// Unbekannt: stehen lassen, falls du das lieber willst -> return $m[0];
			return '';
		}

		$choice = Pick($assets[$key]);           // z. B. "happily" oder "im Moor"
		return Expand($choice, $assets, $depth - 1);
	}, $s);
}

/// @brief Erzeugt einen Titel
function GenerateTitle(array $templates, array $assets): string
{
	$tpl = Pick($templates);
	return RemoveRedundantSpaces(Expand($tpl, $assets));
}


// ======== Controller ========

try
{
	// Import data
	$data = LoadData(__DIR__ . '/tkkg_data.json');
	$templates = $data['templates'] ?? [];
	$assets = $data['assets'] ?? [];
	$loaded = is_array($data) && !empty($data) && !empty($templates) && !empty($assets);

	// Parameters
	$count = isset($_GET['count']) ? max(1, (int)$_GET['count']) : 10;

	# Seed random generator
	mt_srand((int)microtime(true) * 1000000);
}
catch (Throwable $e)
{
	header('Content-Type: text/plain; charset=utf-8', true, 500);
	echo 'Fehler: ' . $e->getMessage() . '<br/>' . $e->getTraceAsString();
}

?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars(SCRIPTTITLE . ' ' . SCRIPTVERSION, ENT_QUOTES) ?></title>
	<style>
		/* Standard entities */
		body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 2rem; }
		fieldset { padding: 1rem; border-radius: 8px; }
		label { display: block; margin: 0.5rem 0 0.25rem; }
		input[type="number"] { width: 7rem; }
		pre { background: #111; color: #0f0; padding: 1rem; border-radius: 8px; overflow: auto; }
		button { padding: .6rem 1rem; border-radius: 10px; border: 1px solid #ccc; background: #f6f6f6; cursor: pointer; -webkit-appearance: none; appearance: none; -webkit-text-fill-color: #111; color: #111; }
		button:hover { background: #eee; }
		button.save { padding: 0 .5rem; border-radius: 8px; border: 1px solid #ccc; background: #f6f6f6; cursor: pointer; -webkit-appearance: none; appearance: none; -webkit-text-fill-color: #111; color: #111; }
		button.save.saved::after { content: ' Saved'; font-size: .85em; color: #3a7; margin-left: .25rem; }
		hr { border: none; height: 1px; background: #ddd; margin: 1rem 0; }
		/* Custom classes */
		.err { background: #fee; color: #900; padding: 0.75rem; border: 1px solid #f99; border-radius: 8px; }
		.grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); gap: 1rem; }
		.range-row { display: grid; grid-template-columns: 1fr 70px; align-items: center; gap: .75rem; }
		.range-row output { text-align: right; font-variant-numeric: tabular-nums; }
		.small { color: #666; font-size: .9rem; }
		/* Collapsible parameters */
		details.params { border: 1px solid #ddd; border-radius: 8px; padding: .5rem .75rem; background: #fafafa; margin-top: 1rem; margin-bottom: 1rem; }
		details.params > summary { cursor: pointer; user-select: none; display: flex; align-items: center; gap: .5rem; font-weight: 600; outline: none; list-style: none;}
		details.params > summary::-webkit-details-marker { display: none; }
		details.params > summary::before { content: '▸'; transition: transform .15s ease-in-out; }
		details.params[open] > summary::before { transform: rotate(90deg); }
		details.params .content { margin-top: .75rem; }
		/* Result list */
		.results { list-style: none; padding: 0; margin: 0; }
		.results li { display: flex; align-items: center; gap: .5rem; padding: .25rem 0; border-bottom: 1px dashed #eee; }
		.results .idx { color: #888; min-width: 2.5rem; text-align: right; }
		.results .val { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
	</style>
	<script>
	// LocalStorage keys
	const APP_KEY = 'tkkgfolgentitelgen'
	const FAVS_KEY = APP_KEY + '.favorites';

	// Favorites

	function loadFavs()
	{
		try
		{
			const raw = localStorage.getItem(FAVS_KEY);
			if (!raw) { return []; }
			const arr = JSON.parse(raw);
			return Array.isArray(arr) ? arr : [];
		}
		catch (_)
		{
			return [];
		}
	}

	function saveFavs(list)
	{
		try
		{
			localStorage.setItem(FAVS_KEY, JSON.stringify(list));
		}
		catch (_)
		{
			/* ignore quota errors */
		}
	}

	function addFav(name)
	{
		const list = loadFavs();
		if (!list.includes(name))
		{
			list.push(name);
			saveFavs(list);
		}
		renderFavs();
	}

	function removeFav(name)
	{
		const list = loadFavs().filter(v => v !== name);
		saveFavs(list);
		renderFavs();
	}

	function renderFavs()
	{
		const ul = document.getElementById('favlist');
		if (!ul) { return; }
		const favs = loadFavs();
		ul.innerHTML = '';
		favs.forEach((v, i) =>
		{
			const li = document.createElement('li');
			const idx = document.createElement('span');
			idx.className = 'idx';
			idx.textContent = String(i + 1).padStart(2, ' ') + '. ';

			const val = document.createElement('span');
			val.className = 'val';
			val.textContent = v;

			const btn = document.createElement('button');
			btn.className = 'save saved';
			btn.textContent = '★';
			btn.title = 'Von Favoriten entfernen';
			btn.addEventListener('click', function ()
			{
				removeFav(v);
			});

			li.appendChild(btn);
			li.appendChild(idx);
			li.appendChild(val);
			ul.appendChild(li);
		});
	}

	// On DOM ready
	document.addEventListener('DOMContentLoaded', function ()
	{
		// Favorites: Wire “Save” buttons in results
		document.querySelectorAll('#results .save').forEach(btn =>
		{
			btn.addEventListener('click', function ()
			{
				const name = btn.getAttribute('data-name') || '';
				if (name)
				{
					addFav(name);
					btn.classList.add('saved');
					btn.textContent = '★';
					btn.title = 'Gespeichert';
				}
			});
		});
		// Favorites toolbar
		const btnCopy = document.getElementById('fav-copy');
		if (btnCopy)
		{
			btnCopy.addEventListener('click', async function ()
			{
				const text = loadFavs().join('\n');
				try
				{
					await navigator.clipboard.writeText(text);
				}
				catch (_)
				{
					/* clipboard may be blocked; ignore */
				}
			});
		}

		const btnExport = document.getElementById('fav-export');
		if (btnExport)
		{
			btnExport.addEventListener('click', function ()
			{
				const text = loadFavs().join('\n');
				const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
				const url = URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = APP_KEY + '-favorites.txt';
				document.body.appendChild(a);
				a.click();
				setTimeout(function ()
				{
					URL.revokeObjectURL(url);
					document.body.removeChild(a);
				}, 0);
			});
		}

		const btnClear = document.getElementById('fav-clear');
		if (btnClear)
		{
			btnClear.addEventListener('click', function ()
			{
				if (confirm('Favoritenliste leeren?'))
				{
					saveFavs([]);
					renderFavs();
				}
			});
		}

		// Initial render
		renderFavs();
	});
	</script>
</head>
<body>
	<h1><?= htmlspecialchars(SCRIPTTITLE . ' ' . SCRIPTVERSION, ENT_QUOTES) ?></h1>

	<!-- Input and parameters form -->

	<form id="form" method="get">
		<fieldset class="grid">
			<div>
				<label for="count">Anzahl</label>
				<input id="count" name="count" type="number" min="1" step="1" value="<?= (int)$count ?>">
			</div>
		</fieldset>

		<p style="margin-top:1rem; display:flex; gap:.5rem; flex-wrap:wrap">
			<button type="submit">Generieren!</button>
		</p>
	</form>

	<!-- Results or error message -->

	<?php if (!$loaded): ?>
		<p class="err">Konnte <code><?= htmlspecialchars(DATAFILENAME, ENT_QUOTES) ?></code> im aktuellen Ordner nicht laden.</p>
	<?php else: ?>
		<h2><?php echo $count; ?> TKKG-Folgentitel:</h2>
		<ul id="results" class="results">
		<?php
			for ($i = 0; $i < $count; ++$i)
			{
				$idx = $i + 1;
				$title = GenerateTitle($templates, $assets);
				$label = ($count > 1) ? (str_pad((string)$idx, 2, ' ', STR_PAD_LEFT) . '. ') : '';
				echo '<li>';
				echo    '<button class="save" data-name="' . htmlspecialchars($title, ENT_QUOTES) . '" title="Favorit speichern">☆</button> ';
				echo    '<span class="idx">' . htmlspecialchars($label, ENT_QUOTES) . '</span>';
				echo    '<span class="val">' . htmlspecialchars($title, ENT_QUOTES) . '</span>';
				echo '</li>';
			}
		?>
		</ul>
	<?php endif; ?>

	<!-- Favorites list -->

	<details id="favorites" class="params">
		<summary>Favoriten</summary>
		<div class="content">
			<ul id="favlist" class="results"></ul>
			<p style="margin-top:.75rem; display:flex; gap:.5rem; flex-wrap:wrap">
				<button type="button" id="fav-copy">Kopieren</button>
				<button type="button" id="fav-export">Exportiere .txt</button>
				<button type="button" id="fav-clear">Leeren</button>
			</p>
		</div>
	</details>
	<p class="footer">&copy; 2025 by <a href="https://www.frankwilleke.de">www.frankwilleke.de</a></p>
</body>
</html>
