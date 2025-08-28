<?php


session_start();

function evaluar_expresion(string $entrada)
{
    
    $expr = str_replace(["×", "÷", ","], ["*", "/", "."], $entrada);


    $expr = preg_replace('/\^/', '**', $expr);

    $expr = preg_replace('/\bpi\b|π/u', '('.M_PI.')', $expr);
    // Reemplazar ln(x) por log(x) (log natural en PHP)
    $expr = preg_replace('/\bln\s*\(/', 'log(', $expr);
    // Asegurar e como constante solo cuando sea aislada (no en "exp")
    $expr = preg_replace('/(?<![a-zA-Z_])e(?![a-zA-Z_0-9])/', '('.M_E.')', $expr);

    $expr = trim($expr);

    if ($expr === '') return '';

    // 3) Lista blanca de tokens permitidos
    $permitidos_func = '(sin|cos|tan|asin|acos|atan|sqrt|abs|log|exp|pow|round|floor|ceil|min|max)';
    // Solo dígitos, espacios, punto, paréntesis, operadores + - * / % ** y comas en argumentos
    $patron = '/^\s*[0-9\.\s\(\),+\-*\/%]*\s*|[a-zA-Z]+\s*\(.*\)\s*$/u';

    // 4) Validación estricta carácter por carácter (lista blanca)
    if (preg_match('/[^0-9\s\.+\-*\/%,\(\)a-zA-Z_π\*]/u', $expr)) {
        throw new Exception('Caracter no permitido.');
    }

    // 5) Bloquear identificadores no permitidos
    // Extraer posibles palabras y validar contra lista blanca
    if (preg_match_all('/[a-zA-Z_]+/', $expr, $m)) {
        $palabras = $m[0];
        foreach ($palabras as $w) {
            if (!preg_match('/^'.$permitidos_func.'$/', $w)) {
                throw new Exception('Función no permitida: '.htmlspecialchars($w));
            }
        }
    }

    // 6) Seguridad mínima en paréntesis balanceados
    $balance = 0;
    $len = strlen($expr);
    for ($i=0; $i<$len; $i++) {
        $c = $expr[$i];
        if ($c === '(') $balance++;
        if ($c === ')') $balance--;
        if ($balance < 0) throw new Exception('Paréntesis desbalanceados.');
    }
    if ($balance !== 0) throw new Exception('Paréntesis desbalanceados.');

    // 7) Evaluación delimitada
    // Convertir porcentajes tipo "50%" a (50/100)
    $expr = preg_replace('/(\d+(?:\.\d+)?)%/', '($1/100)', $expr);

    // Reemplazar pow(a,b) explícitamente por pow(a,b) de PHP (ya permitido)
    // No se requiere cambio adicional.

    // Evaluar
    set_error_handler(function(){ /* silenciar avisos para capturarlos como excepción */ });
    try {
        // Limitar tiempo de ejecución de forma básica
        $code = 'return '.$expr.';';
        $resultado = @eval($code);
        if ($resultado === false && $resultado !== 0) {
            throw new Exception('Expresión inválida.');
        }
    } finally {
        restore_error_handler();
    }

    return $resultado;
}

$resultado = null; $error = null; $expr_in = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expr_in = $_POST['expr'] ?? '';
    try {
        $resultado = evaluar_expresion($expr_in);
        if (!isset($_SESSION['hist'])) $_SESSION['hist'] = [];
        if ($expr_in !== '') {
            array_unshift($_SESSION['hist'], [date('H:i:s'), $expr_in, $resultado]);
            $_SESSION['hist'] = array_slice($_SESSION['hist'], 0, 12); // últimas 12
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calculadora</title>
  <style>
    :root{
      --bg: radial-gradient(circle at 10% 10%, #1e3c72 0%, #000000ff 25%, #0f2027 60%, #000000ff 100%);
      --card: rgba(255,255,255,0.1);
      --glass: rgba(255,255,255,0.08);
      --border: rgba(255,255,255,0.2);
      --txt: #eef3ff;
      --accent: #7dd3fc; /* light sky */
      --accent-2: #a78bfa; /* violet */
      --ok: #86efac; --warn: #facc15; --err: #f87171;
    }
    *{box-sizing:border-box}
    body{
      margin:0; min-height:100vh; color:var(--txt); font-family:ui-sans-serif,system-ui,Segoe UI,Roboto,Ubuntu,Cantarell,'Helvetica Neue',Arial; background:var(--bg);
      display:grid; place-items:center; padding:24px;
    }
    .wrap{ width: min(980px, 100%); display:grid; gap:24px; }
    .title{ text-align:center; }
    .title h1{ margin:0; font-size:clamp(22px,3.6vw,40px); letter-spacing:.5px; }
    .title p{ opacity:.9; margin:.25rem 0 0 0; }

    .calc{
      display:grid; grid-template-columns: 1fr 340px; gap:24px;
    }
    @media (max-width: 880px){ .calc{ grid-template-columns: 1fr; } }

    .panel{
      backdrop-filter: blur(10px); background:var(--card); border:1px solid var(--border);
      border-radius:20px; box-shadow: 0 10px 30px rgba(0,0,0,.3); overflow:hidden;
    }
    .pad{ padding:18px; }

    .display{
      padding:20px; font-size:clamp(18px,2.5vw,28px); min-height:72px; word-wrap:anywhere; display:flex; align-items:center; justify-content:space-between; gap:12px;
      background: linear-gradient( to bottom right, rgba(255,255,255,.08), rgba(255,255,255,.03));
      border-bottom:1px solid var(--border);
    }
    .display .expr{ opacity:.95; flex:1 }
    .display .res{ font-weight:700; font-variant-numeric:tabular-nums; }

    .grid{ display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; }
    button, .btn{
      appearance:none; border:1px solid var(--border); background:var(--glass);
      color:var(--txt); font-size:18px; padding:14px; border-radius:16px; cursor:pointer;
      transition: transform .05s ease, background .2s ease, box-shadow .2s ease;
    }
    button:hover{ background: rgba(255,255,255,.14); }
    button:active{ transform: scale(.98); }
    .b-big{ grid-column: span 2; }
    .b-op{ border-color: rgba(125, 211, 252, .4); }
    .b-eq{ background: linear-gradient(135deg, var(--accent), var(--accent-2)); color:#0b1020; font-weight:800; }
    .b-eq:hover{ filter:brightness(1.04); }

    .side{ display:grid; gap:16px; }

    .hist{ max-height:410px; overflow:auto; }
    .hist table{ width:100%; border-collapse:collapse; font-size:14px; }
    .hist th, .hist td{ padding:10px; border-bottom:1px dashed var(--border); text-align:left; }
    .hist tr:hover{ background: rgba(255,255,255,.06); }
    .muted{ opacity:.8 }
    .badge{ display:inline-block; padding:.2rem .5rem; border-radius:999px; border:1px solid var(--border); background:rgba(255,255,255,.08); font-size:12px; }

    .footer{ text-align:center; opacity:.8; font-size:13px; }
    input[type="hidden"], form{ margin:0 }
    .note{ font-size:12px; opacity:.85; }
    .err{ color:var(--err); font-weight:600 }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="title">
      <h1>Calculadora PHP ✨</h1>
    </div>

    <div class="calc">
      <!-- Panel principal -->
      <section class="panel">
        <div class="display">
          <div class="expr" id="exprVisual">0</div>
          <div class="res" id="resVisual">&nbsp;</div>
        </div>
        <div class="pad">
          <div class="grid">
            <button class="b-op" data-act="clear">AC</button>
            <button class="b-op" data-inp="( ">(</button>
            <button class="b-op" data-inp=") ">)</button>
            <button class="b-op" data-inp="%">%</button>

            <button class="b-op" data-inp="7">7</button>
            <button class="b-op" data-inp="8">8</button>
            <button class="b-op" data-inp="9">9</button>
            <button class="b-op" data-inp="÷">÷</button>

            <button class="b-op" data-inp="4">4</button>
            <button class="b-op" data-inp="5">5</button>
            <button class="b-op" data-inp="6">6</button>
            <button class="b-op" data-inp="×">×</button>

            <button class="b-op" data-inp="1">1</button>
            <button class="b-op" data-inp="2">2</button>
            <button class="b-op" data-inp="3">3</button>
            <button class="b-op" data-inp="-">-</button>

            <button class="b-op" data-inp="0">0</button>
            <button class="b-op" data-inp=".">.</button>
            <button class="b-op" data-inp="+">+</button>
            <button class="b-eq" data-act="eval">=</button>

        
            <button class="b-op" data-act="back">⌫</button>

         

           
            <button class="b-op" data-inp=",">,</button>
            <form method="POST" style="display:contents" id="postForm">
              <input type="hidden" name="expr" id="postExpr">
          
            </form>
          </div>
          <?php if ($error): ?>
            <div class="pad"><div class="note err">Error PHP: <?= htmlspecialchars($error) ?></div></div>
          <?php elseif ($resultado !== null): ?>
            <div class="pad"></div>
          <?php else: ?>
            <div class="pad"></div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Lateral: Historial y ayuda -->
     
    </div>
  </div>

  <script>
    const exprEl = document.getElementById('exprVisual');
    const resEl  = document.getElementById('resVisual');
    const form   = document.getElementById('postForm');
    const postExpr = document.getElementById('postExpr');

    let expr = '';

    const sanitizeForEval = s => s
      .replace(/×/g,'*')
      .replace(/÷/g,'/')
      .replace(/π/g, Math.PI)
      .replace(/\^/g,'**')
      .replace(/\bln\s*\(/g,'Math.log(')
      .replace(/\blog\s*\(/g,'Math.log10(')
      .replace(/\bsin\s*\(/g,'Math.sin(')
      .replace(/\bcos\s*\(/g,'Math.cos(')
      .replace(/\btan\s*\(/g,'Math.tan(')
      .replace(/\bsqrt\s*\(/g,'Math.sqrt(')
      .replace(/(?<![a-zA-Z_])e(?![a-zA-Z_0-9])/g, Math.E);

    function render(){
      exprEl.textContent = expr || '0';
    }

    function evaluateClient(){
      try{
        const js = sanitizeForEval(expr).replace(/(\d+(?:\.\d+)?)%/g, '($1/100)');
        const out = Function('return ('+js+')')();
        if (out === undefined) throw new Error('Expresión inválida');
        resEl.textContent = Number(out).toPrecision(10).replace(/\.0+$/,'');
      }catch(e){
        resEl.textContent = '…';
      }
    }

    function input(v){ expr += v; render(); evaluateClient(); }

    function back(){ expr = expr.slice(0,-1); render(); evaluateClient(); }

    function clearAll(){ expr = ''; resEl.textContent = '\u00A0'; render(); }

    function eq(){ // usa resultado cliente si es válido
      try{
        const js = sanitizeForEval(expr).replace(/(\d+(?:\.\d+)?)%/g, '($1/100)');
        const out = Function('return ('+js+')')();
        if (!Number.isFinite(out)) throw new Error('NaN');
        expr = String(out);
        render();
        resEl.textContent = '\u00A0';
      }catch{ resEl.textContent = 'Err'; }
    }

    document.addEventListener('click', e=>{
      const b = e.target.closest('button');
      if(!b) return;
      if(b.dataset.inp){ input(b.dataset.inp); }
      if(b.dataset.act === 'back'){ back(); }
      if(b.dataset.act === 'clear'){ clearAll(); }
      if(b.dataset.act === 'eval'){ eq(); }
      if(b.type === 'submit'){
        postExpr.value = expr; // enviar al servidor la expresión actual
      }
    });

    document.addEventListener('keydown', e=>{
      const map = {
        'Enter': eq,
        'Backspace': back,
        'Escape': clearAll
      };
      if(map[e.key]){ e.preventDefault(); map[e.key](); return; }
      const allowed = '0123456789+-*/%^().,';
      if(allowed.includes(e.key)) { input(e.key); return; }
      if(e.key.toLowerCase()==='p'){ input('π'); return; }
    });

    render();
  </script>
</body>
</html>
