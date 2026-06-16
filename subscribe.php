<?php
/**
 * subscribe.php — Public subscription signup + payment + tax invoice.
 */
require_once __DIR__ . '/config/env.php';
Env::load();

$projectRoot = rtrim(Env::getProjectRoot(), '/');
$token       = trim($_GET['t'] ?? '');

$planName      = '';
$planBilling   = 'monthly';
$planAmount    = 0;
$planCurrency  = 'INR';
$planTrial     = 0;
$planMessage   = '';
$taxTreatment  = 'none';
$taxRate       = 18;
$tokenError    = '';

if (!$token) {
    $tokenError = 'No subscription link provided.';
} else {
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        $tokenError = 'This link is invalid.';
    } else {
        [$payloadB64, $sig] = $parts;
        $secret      = Env::get('RAZORPAY_KEY_SECRET') ?: 'crm-sub-link-secret-fallback';
        $expectedSig = substr(hash_hmac('sha256', $payloadB64, $secret), 0, 32);
        if (!hash_equals($expectedSig, $sig)) {
            $tokenError = 'This link appears to have been tampered with.';
        } else {
            $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
            if (!$payload) {
                $tokenError = 'This link is corrupt.';
            } elseif (($payload['exp'] ?? 0) < time()) {
                $tokenError = 'This subscription link has expired. Please contact your administrator.';
            } else {
                $planName     = $payload['plan_name'] ?? 'Subscription';
                $planBilling  = $payload['billing']   ?? 'monthly';
                $planAmount   = (float)($payload['amount']   ?? 0);
                $planCurrency = strtoupper($payload['currency'] ?? 'INR');
                $planTrial    = (int)($payload['trial_days']  ?? 0);
                $planMessage  = trim($payload['msg'] ?? '');
                $taxTreatment = $payload['tax_treatment'] ?? 'none';
                $taxRate      = (float)($payload['tax_rate'] ?? 18);
            }
        }
    }
}

// Compute amounts for display
$taxAmount   = 0;
$totalAmount = $planAmount;
if ($taxTreatment === 'exclusive' && $planAmount > 0) {
    $taxAmount   = round($planAmount * ($taxRate / 100), 2);
    $totalAmount = $planAmount + $taxAmount;
} elseif ($taxTreatment === 'inclusive' && $planAmount > 0) {
    $taxAmount = round($planAmount - ($planAmount / (1 + $taxRate / 100)), 2);
}

$isFree  = ($totalAmount <= 0);
$fmt     = fn($n) => $planCurrency . ' ' . number_format($n, 2);

$apiBase       = 'http' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $projectRoot . '/api';
$tokenJs       = json_encode($token);
$apiBaseJs     = json_encode($apiBase);
$projectRootJs = json_encode($projectRoot);
$loginUrl      = $projectRoot . '/login.php';

// PHP vars for JS
$jsVars = json_encode([
    'planName'     => $planName,
    'planBilling'  => $planBilling,
    'planAmount'   => $planAmount,
    'planCurrency' => $planCurrency,
    'planTrial'    => $planTrial,
    'taxTreatment' => $taxTreatment,
    'taxRate'      => $taxRate,
    'taxAmount'    => $taxAmount,
    'totalAmount'  => $totalAmount,
    'isFree'       => $isFree,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Started — <?= htmlspecialchars($planName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if (!$tokenError && !$isFree): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <?php endif; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        .field-label { display:block; font-size:0.78rem; font-weight:600; color:#374151; margin-bottom:6px; }
        .field-input { width:100%; border:1.5px solid #e5e7eb; border-radius:10px; padding:11px 14px; font-size:0.9rem; color:#111827; outline:none; transition:border-color 0.15s,box-shadow 0.15s; }
        .field-input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.08); }
        .field-input.has-toggle { padding-right:44px; }
        .toggle-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#9ca3af; background:none; border:none; padding:2px; line-height:0; }
        .toggle-eye:hover { color:#6b7280; }
        .btn-primary { width:100%; background:#2563eb; color:#fff; border:none; padding:13px; border-radius:10px; font-size:0.95rem; font-weight:600; cursor:pointer; transition:background 0.15s; display:flex; align-items:center; justify-content:center; gap:8px; }
        .btn-primary:hover:not(:disabled) { background:#1d4ed8; }
        .btn-primary:disabled { background:#93c5fd; cursor:not-allowed; }
        .step { display:none; }
        .step.active { display:block; }
        .spinner { animation:spin 0.8s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        .error-banner { background:#fef2f2; border:1.5px solid #fecaca; border-radius:10px; padding:12px 16px; font-size:0.875rem; color:#dc2626; display:none; }
        .error-banner.visible { display:block; }

        /* Invoice styles */
        @media print {
            body > div > .invoice-wrap { display: block !important; }
            body > * { display: none; }
            .invoice-wrap { display: block !important; position: static !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body style="background:linear-gradient(135deg,#f0f4ff 0%,#e8f0fe 50%,#f0fdf4 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;">

<div style="width:100%;max-width:900px;">

<?php if ($tokenError): ?>
<div style="background:#fff;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,0.10);padding:60px 40px;text-align:center;max-width:440px;margin:0 auto;">
    <div style="width:64px;height:64px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
        <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#ef4444" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </div>
    <h2 style="font-size:1.4rem;font-weight:700;color:#111827;margin:0 0 8px;">Invalid Link</h2>
    <p style="color:#6b7280;font-size:0.9rem;line-height:1.6;"><?= htmlspecialchars($tokenError) ?></p>
</div>

<?php else: ?>
<div style="background:#fff;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,0.10);overflow:hidden;display:flex;">

    <!-- Left: Plan info -->
    <div style="background:linear-gradient(160deg,#1e3a8a 0%,#1e40af 40%,#2563eb 100%);color:#fff;width:300px;flex-shrink:0;padding:36px 28px;display:flex;flex-direction:column;">
        <div style="margin-bottom:28px;">
            <div style="width:40px;height:40px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <p style="font-size:0.7rem;color:rgba(255,255,255,0.55);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:5px;">You're signing up for</p>
            <h2 style="font-size:1.55rem;font-weight:800;margin:0;line-height:1.2;"><?= htmlspecialchars($planName) ?></h2>
        </div>

        <!-- Price breakdown -->
        <div style="background:rgba(255,255,255,0.1);border-radius:12px;padding:16px 18px;margin-bottom:14px;">
            <?php if ($isFree): ?>
                <p style="font-size:0.7rem;color:rgba(255,255,255,0.5);margin:0 0 4px;text-transform:uppercase;letter-spacing:0.08em;">Pricing</p>
                <p style="font-size:1.6rem;font-weight:700;margin:0;"><?= $planTrial > 0 ? "{$planTrial}-day Free Trial" : 'Free' ?></p>
            <?php else: ?>
                <?php if ($taxTreatment === 'exclusive'): ?>
                    <div style="border-bottom:1px solid rgba(255,255,255,0.12);padding-bottom:10px;margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:5px;">
                            <span>Base price</span><span><?= $fmt($planAmount) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.83rem;color:rgba(255,255,255,0.7);">
                            <span>GST (<?= $taxRate ?>%)</span><span><?= $fmt($taxAmount) ?></span>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.78rem;color:rgba(255,255,255,0.55);">Total / <?= $planBilling ?></span>
                        <span style="font-size:1.4rem;font-weight:700;"><?= $fmt($totalAmount) ?></span>
                    </div>
                <?php elseif ($taxTreatment === 'inclusive'): ?>
                    <p style="font-size:0.7rem;color:rgba(255,255,255,0.5);margin:0 0 4px;text-transform:uppercase;letter-spacing:0.08em;">Total / <?= $planBilling ?></p>
                    <p style="font-size:1.6rem;font-weight:700;margin:0 0 6px;"><?= $fmt($planAmount) ?></p>
                    <p style="font-size:0.75rem;color:rgba(255,255,255,0.5);margin:0;">Incl. GST (<?= $taxRate ?>%): <?= $fmt($taxAmount) ?></p>
                <?php else: ?>
                    <p style="font-size:0.7rem;color:rgba(255,255,255,0.5);margin:0 0 4px;text-transform:uppercase;letter-spacing:0.08em;">Total / <?= $planBilling ?></p>
                    <p style="font-size:1.6rem;font-weight:700;margin:0;"><?= $fmt($planAmount) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($planTrial > 0 && !$isFree): ?>
        <div style="background:rgba(52,211,153,0.15);border:1px solid rgba(52,211,153,0.3);border-radius:10px;padding:10px 14px;margin-bottom:14px;">
            <p style="font-size:0.82rem;color:#6ee7b7;margin:0;">✓ <?= $planTrial ?>-day free trial — no charge during trial</p>
        </div>
        <?php endif; ?>

        <?php if ($planMessage): ?>
        <div style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);padding-top:18px;">
            <p style="font-size:0.7rem;color:rgba(255,255,255,0.45);margin:0 0 5px;text-transform:uppercase;letter-spacing:0.08em;">Note</p>
            <p style="font-size:0.85rem;color:rgba(255,255,255,0.8);line-height:1.6;margin:0;"><?= htmlspecialchars($planMessage) ?></p>
        </div>
        <?php else: ?>
        <div style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);padding-top:18px;">
            <?php if (!$isFree): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="rgba(255,255,255,0.4)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span style="font-size:0.73rem;color:rgba(255,255,255,0.4);">Secure checkout via Razorpay</span>
            </div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:8px;">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="rgba(255,255,255,0.4)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span style="font-size:0.73rem;color:rgba(255,255,255,0.4);">GST invoice provided after payment</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Steps -->
    <div style="flex:1;padding:36px;">

        <!-- Step 1: Form -->
        <div id="step1" class="step active">
            <h3 style="font-size:1.25rem;font-weight:700;color:#111827;margin:0 0 4px;">Create your account</h3>
            <p style="font-size:0.875rem;color:#6b7280;margin:0 0 22px;">Enter your details to get started</p>

            <div id="formError" class="error-banner" style="margin-bottom:14px;"></div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label class="field-label">Organization Name *</label>
                    <input id="orgName" class="field-input" type="text" placeholder="Acme Corp" autocomplete="organization">
                </div>
                <div>
                    <label class="field-label">Your Full Name *</label>
                    <input id="fullName" class="field-input" type="text" placeholder="John Smith" autocomplete="name">
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label class="field-label">Work Email *</label>
                <input id="email" class="field-input" type="email" placeholder="you@company.com" autocomplete="email">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div style="position:relative;">
                    <label class="field-label">Password *</label>
                    <input id="password" class="field-input has-toggle" type="password" placeholder="Min 6 characters" autocomplete="new-password">
                    <button type="button" class="toggle-eye" onclick="togglePwd('password','eye1')" tabindex="-1">
                        <svg id="eye1" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
                <div style="position:relative;">
                    <label class="field-label">Confirm Password *</label>
                    <input id="confirmPassword" class="field-input has-toggle" type="password" placeholder="Re-enter password" autocomplete="new-password">
                    <button type="button" class="toggle-eye" onclick="togglePwd('confirmPassword','eye2')" tabindex="-1">
                        <svg id="eye2" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <label class="field-label">Phone <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                <input id="phone" class="field-input" type="tel" placeholder="+91 98765 43210" autocomplete="tel">
            </div>

            <button id="submitBtn" class="btn-primary" onclick="handleSubmit()">
                <?php if ($isFree): ?>
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                Create My Account
                <?php else: ?>
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                Pay <?= $fmt($totalAmount) ?> &amp; Create Account
                <?php endif; ?>
            </button>
            <p style="text-align:center;font-size:0.76rem;color:#9ca3af;margin-top:12px;">
                Already have an account? <a href="<?= htmlspecialchars($loginUrl) ?>" style="color:#2563eb;">Sign in</a>
            </p>
        </div>

        <!-- Step 2: Processing -->
        <div id="step2" class="step" style="text-align:center;padding:50px 20px;">
            <svg class="spinner" style="display:inline-block;margin-bottom:18px;" width="40" height="40" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="#e5e7eb" stroke-width="3"/>
                <path d="M12 2a10 10 0 0110 10" stroke="#2563eb" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <p id="step2Msg" style="font-size:1rem;color:#374151;font-weight:500;margin:0;">Processing…</p>
            <p style="font-size:0.83rem;color:#9ca3af;margin:8px 0 0;">This will only take a moment</p>
        </div>

        <!-- Step 3: Success + Credentials + Invoice -->
        <div id="step3" class="step">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="width:56px;height:56px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#16a34a" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 style="font-size:1.2rem;font-weight:700;color:#111827;margin:0 0 4px;">You're all set!</h3>
                <p style="font-size:0.85rem;color:#6b7280;margin:0;">Your account has been created successfully.</p>
            </div>

            <!-- Credentials -->
            <div style="background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:12px;padding:16px;margin-bottom:14px;">
                <p style="font-size:0.7rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 10px;">Login Credentials</p>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:0.75rem;color:#64748b;font-weight:600;">Email</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span id="credEmail" style="font-size:0.88rem;color:#0c4a6e;font-family:monospace;font-weight:500;"></span>
                        <button onclick="copyEl('credEmail',this)" style="background:none;border:1px solid #7dd3fc;border-radius:6px;padding:2px 8px;font-size:0.7rem;color:#0369a1;cursor:pointer;">Copy</button>
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:0.75rem;color:#64748b;font-weight:600;">Password</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span id="credPassword" style="font-size:0.88rem;color:#0c4a6e;font-family:monospace;font-weight:500;"></span>
                        <button onclick="copyEl('credPassword',this)" style="background:none;border:1px solid #7dd3fc;border-radius:6px;padding:2px 8px;font-size:0.7rem;color:#0369a1;cursor:pointer;">Copy</button>
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.75rem;color:#64748b;font-weight:600;">Organization</span>
                    <span id="credOrg" style="font-size:0.88rem;color:#0c4a6e;font-family:monospace;font-weight:500;"></span>
                </div>
            </div>

            <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:10px 14px;margin-bottom:16px;">
                <p style="font-size:0.8rem;color:#92400e;margin:0;"><strong>⚠ Save your password now.</strong> It won't be shown again for security.</p>
            </div>

            <div style="display:flex;gap:10px;">
                <button onclick="goToDashboard()" class="btn-primary" style="flex:2;">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Go to Dashboard
                </button>
                <button onclick="showInvoice()" class="btn-primary" style="flex:1;background:#059669;" id="invoiceBtn">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Invoice
                </button>
            </div>
        </div>

        <!-- Step 4: Error -->
        <div id="step4" class="step" style="text-align:center;padding:40px 0;">
            <div style="width:56px;height:56px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#ef4444" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <h3 style="font-size:1.15rem;font-weight:700;color:#111827;margin:0 0 8px;">Something went wrong</h3>
            <p id="step4Msg" style="font-size:0.875rem;color:#6b7280;margin:0 0 20px;line-height:1.6;"></p>
            <button onclick="showStep(1)" style="background:none;border:1.5px solid #2563eb;color:#2563eb;padding:10px 24px;border-radius:10px;font-size:0.9rem;font-weight:600;cursor:pointer;">← Try Again</button>
        </div>

    </div>
</div>

<!-- ── Tax Invoice Modal ────────────────────────────────────────────────────── -->
<div id="invoiceModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;overflow-y:auto;padding:20px;">
    <div style="background:#fff;max-width:680px;margin:20px auto;border-radius:12px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div class="no-print" style="background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:600;color:#374151;font-size:0.95rem;">Tax Invoice</span>
            <div style="display:flex;gap:10px;">
                <button onclick="savePDF(this)" style="background:#2563eb;color:#fff;border:none;padding:7px 16px;border-radius:8px;font-size:0.83rem;cursor:pointer;font-weight:500;">Download PDF</button>
                <button onclick="document.getElementById('invoiceModal').style.display='none'" style="background:none;border:1px solid #d1d5db;padding:7px 14px;border-radius:8px;font-size:0.83rem;cursor:pointer;">Close</button>
            </div>
        </div>
        <div id="invoiceContent" style="padding:40px 48px;font-size:0.9rem;line-height:1.6;color:#111827;"></div>
    </div>
</div>

<?php endif; ?>
</div>

<script>
const PLAN   = <?= $jsVars ?>;
const TOKEN  = <?= $tokenJs ?>;
const API    = <?= $apiBaseJs ?>;
const ROOT   = <?= $projectRootJs ?>;

let _redirect    = '';
let _formPwd     = '';
let _paymentRef  = '';
let _invoiceData = null;

// ── Helpers ───────────────────────────────────────────────────────────────────
function showStep(n) {
    [1,2,3,4].forEach(i => {
        const el = document.getElementById('step'+i);
        if (el) el.className = 'step' + (i===n?' active':'');
    });
}
function showFormError(msg) {
    const el = document.getElementById('formError');
    el.textContent = msg; el.className = 'error-banner visible';
}
function clearFormError() {
    const el = document.getElementById('formError');
    if(el){el.textContent='';el.className='error-banner';}
}
function setSubmitBusy(busy) {
    const btn = document.getElementById('submitBtn');
    if(!btn) return;
    btn.disabled = busy;
    if(busy){
        btn.innerHTML = '<svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/><path d="M12 2a10 10 0 0110 10" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg> Processing…';
    } else {
        const lbl = PLAN.isFree
            ? '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg> Create My Account'
            : `<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg> Pay ${PLAN.planCurrency} ${PLAN.totalAmount.toFixed(2)} &amp; Create Account`;
        btn.innerHTML = lbl;
    }
}
function togglePwd(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    const icon = document.getElementById(iconId);
    icon.innerHTML = isText
        ? '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>'
        : '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
}
function copyEl(elId, btn) {
    const val = document.getElementById(elId)?.textContent;
    if(!val) return;
    navigator.clipboard.writeText(val).then(()=>{
        const o=btn.textContent; btn.textContent='Copied!'; setTimeout(()=>{btn.textContent=o;},1500);
    }).catch(()=>{
        const r=document.createRange(); r.selectNodeContents(document.getElementById(elId));
        window.getSelection().removeAllRanges(); window.getSelection().addRange(r);
        document.execCommand('copy'); btn.textContent='Copied!'; setTimeout(()=>{btn.textContent='Copy';},1500);
    });
}
function goToDashboard() { if(_redirect) window.location.href=_redirect; }

// ── Form validation ───────────────────────────────────────────────────────────
function getFormData() {
    return {
        org_name:  document.getElementById('orgName').value.trim(),
        full_name: document.getElementById('fullName').value.trim(),
        email:     document.getElementById('email').value.trim().toLowerCase(),
        password:  document.getElementById('password').value,
        phone:     document.getElementById('phone').value.trim(),
    };
}
function validate(d) {
    if(!d.org_name)  return 'Organization name is required.';
    if(!d.full_name) return 'Your name is required.';
    if(!d.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email)) return 'Please enter a valid email address.';
    if(d.password.length<6) return 'Password must be at least 6 characters.';
    if(d.password !== document.getElementById('confirmPassword').value) return 'Passwords do not match.';
    return null;
}

// ── Razorpay (promise-based) ──────────────────────────────────────────────────
function openRazorpay(order, user) {
    return new Promise((resolve, reject) => {
        const rzp = new Razorpay({
            key:         order.key_id,
            order_id:    order.order_id,
            amount:      order.amount,
            currency:    order.currency,
            name:        'CRM Platform',
            description: `${order.plan_name} Subscription`,
            prefill:     { name: user.full_name, email: user.email, contact: user.phone||'' },
            theme:       { color: '#2563EB' },
            handler:     (r) => resolve(r),
            modal:       { ondismiss: () => reject(new Error('Payment was cancelled.')) },
        });
        rzp.on('payment.failed', (r) => reject(new Error(r.error.description || 'Payment failed.')));
        rzp.open();
    });
}

async function postJson(url, body) {
    const res = await fetch(url, {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
    });
    return res.json();
}

// ── Main submit ───────────────────────────────────────────────────────────────
async function handleSubmit() {
    clearFormError();
    const user = getFormData();
    const err  = validate(user);
    if(err){ showFormError(err); return; }
    setSubmitBusy(true);
    _formPwd = user.password;

    try {
        let paymentResponse = null;

        if(!PLAN.isFree) {
            document.getElementById('step2Msg').textContent = 'Initializing payment…';
            showStep(2);
            const orderRes = await postJson(API+'/subscribe/init.php', {token:TOKEN});
            if(!orderRes.success) throw new Error(orderRes.error||'Could not initialize payment.');
            const order = orderRes.data;

            if(!order.free) {
                showStep(1); setSubmitBusy(true);
                paymentResponse = await openRazorpay(order, user);
                _paymentRef = paymentResponse.razorpay_payment_id || '';
                document.getElementById('step2Msg').textContent = 'Verifying payment & creating account…';
                showStep(2);
            }
        } else {
            document.getElementById('step2Msg').textContent = 'Creating your account…';
            showStep(2);
        }

        const res = await postJson(API+'/subscribe/complete.php', {token:TOKEN, ...user, ...(paymentResponse||{})});
        if(!res.success) throw new Error(res.error||'Account creation failed.');

        const result = res.data;
        _redirect = result.redirect;

        // Store invoice data
        _invoiceData = {
            org_name:     user.org_name,
            full_name:    user.full_name,
            email:        user.email,
            plan_name:    PLAN.planName,
            billing:      PLAN.planBilling,
            base_amount:  PLAN.planAmount,
            tax_treatment:PLAN.taxTreatment,
            tax_rate:     PLAN.taxRate,
            tax_amount:   PLAN.taxAmount,
            total_amount: PLAN.totalAmount,
            currency:     PLAN.planCurrency,
            payment_ref:  _paymentRef || 'N/A',
            date:         new Date().toLocaleDateString('en-IN', {day:'2-digit',month:'long',year:'numeric'}),
            invoice_no:   'INV-' + Date.now(),
        };

        // Show success
        document.getElementById('credEmail').textContent    = user.email;
        document.getElementById('credPassword').textContent = _formPwd;
        document.getElementById('credOrg').textContent      = user.org_name;
        if(PLAN.isFree) document.getElementById('invoiceBtn').style.display = 'none';
        showStep(3);

    } catch(e) {
        setSubmitBusy(false);
        if(e.message.includes('cancelled')) {
            showStep(1); showFormError(e.message);
        } else {
            document.getElementById('step4Msg').textContent = e.message;
            showStep(4);
        }
    }
}

// ── Invoice ───────────────────────────────────────────────────────────────────
function showInvoice() {
    if(!_invoiceData) return;
    const d = _invoiceData;
    const fmt = (n) => `${d.currency} ${parseFloat(n).toFixed(2)}`;

    let priceRows = '';
    if(d.tax_treatment === 'exclusive') {
        priceRows = `
            <tr><td style="padding:8px 0;color:#374151;">Base Price</td><td style="padding:8px 0;text-align:right;">${fmt(d.base_amount)}</td></tr>
            <tr><td style="padding:8px 0;color:#374151;">GST (${d.tax_rate}%)</td><td style="padding:8px 0;text-align:right;">${fmt(d.tax_amount)}</td></tr>`;
    } else if(d.tax_treatment === 'inclusive') {
        priceRows = `
            <tr><td style="padding:8px 0;color:#374151;">Subscription Price</td><td style="padding:8px 0;text-align:right;">${fmt(d.total_amount)}</td></tr>
            <tr><td style="padding:8px 0;color:#6b7280;font-size:0.85em;">GST Portion (${d.tax_rate}%)</td><td style="padding:8px 0;text-align:right;color:#6b7280;font-size:0.85em;">${fmt(d.tax_amount)}</td></tr>`;
    } else {
        priceRows = `<tr><td style="padding:8px 0;color:#374151;">Subscription Price</td><td style="padding:8px 0;text-align:right;">${fmt(d.total_amount)}</td></tr>`;
    }

    const taxLabel = d.tax_treatment === 'exclusive'
        ? 'Tax Exclusive (GST added separately)'
        : d.tax_treatment === 'inclusive'
        ? 'Tax Inclusive (GST included in price)'
        : 'No Tax';

    document.getElementById('invoiceContent').innerHTML = `
        <!-- Header -->
        <div style="border-bottom:2px solid #1e3a8a;padding-bottom:18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <h1 style="font-size:1.5rem;font-weight:800;color:#1e3a8a;margin:0 0 2px;">TAX INVOICE</h1>
                <p style="color:#6b7280;font-size:0.8rem;margin:0;">Original for Recipient</p>
            </div>
            <div style="text-align:right;">
                <p style="font-size:0.82rem;color:#374151;margin:0 0 2px;"><strong>Invoice No:</strong> ${d.invoice_no}</p>
                <p style="font-size:0.82rem;color:#374151;margin:0 0 2px;"><strong>Date:</strong> ${d.date}</p>
                <p style="font-size:0.82rem;color:#374151;margin:0;"><strong>Tax Type:</strong> ${taxLabel}</p>
            </div>
        </div>

        <!-- Seller & Buyer -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px;">
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                <p style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 8px;">Sold By</p>
                <p style="font-weight:700;color:#111827;font-size:0.95rem;margin:0 0 3px;">Digifyce</p>
                <p style="color:#374151;font-size:0.82rem;margin:0 0 2px;">No. 01A, New No. 200, Old No. 398</p>
                <p style="color:#374151;font-size:0.82rem;margin:0 0 2px;">SLV DS Centre, Barathiyar Road</p>
                <p style="color:#374151;font-size:0.82rem;margin:0 0 2px;">New Siddhapudur, Coimbatore – 641044</p>
                <p style="color:#374151;font-size:0.82rem;margin:0 0 6px;">Tamil Nadu, India</p>
                <p style="font-size:0.78rem;color:#374151;margin:0;"><strong>GSTIN:</strong> <span style="font-family:monospace;letter-spacing:0.04em;">33BUWPR7004L2ZZ</span></p>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;">
                <p style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 8px;">Billed To</p>
                <p style="font-weight:700;color:#111827;font-size:0.95rem;margin:0 0 3px;">${esc(d.full_name)}</p>
                <p style="color:#374151;font-size:0.82rem;margin:0 0 2px;">${esc(d.org_name)}</p>
                <p style="color:#374151;font-size:0.82rem;margin:0;">${esc(d.email)}</p>
            </div>
        </div>

        <!-- Line items table -->
        <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:0.85rem;">
            <thead>
                <tr style="background:#1e3a8a;">
                    <th style="padding:9px 12px;text-align:left;color:#fff;font-weight:600;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.03em;">Description</th>
                    <th style="padding:9px 12px;text-align:center;color:#fff;font-weight:600;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.03em;">Billing</th>
                    <th style="padding:9px 12px;text-align:right;color:#fff;font-weight:600;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.03em;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                    <td style="padding:12px;">
                        <p style="font-weight:600;color:#111827;margin:0 0 2px;">${esc(d.plan_name)} Subscription</p>
                        <p style="font-size:0.77rem;color:#6b7280;margin:0;">SaaS CRM Platform</p>
                    </td>
                    <td style="padding:12px;text-align:center;color:#374151;text-transform:capitalize;">${d.billing}</td>
                    <td style="padding:12px;text-align:right;font-weight:500;color:#111827;">${fmt(d.base_amount)}</td>
                </tr>
            </tbody>
        </table>

        <!-- Totals + Tax note -->
        <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
            <table style="width:280px;border-collapse:collapse;font-size:0.85rem;">
                <tbody>
                    ${priceRows}
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid #1e3a8a;">
                        <td style="padding:10px 0 10px 8px;font-weight:700;color:#111827;font-size:0.95rem;">Total Paid</td>
                        <td style="padding:10px 8px 10px 0;text-align:right;font-weight:800;color:#1e3a8a;font-size:1.05rem;">${fmt(d.total_amount)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- GST note -->
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.8rem;color:#0369a1;">
            ${d.tax_treatment === 'exclusive'
                ? `<strong>GST Note:</strong> GST @ ${d.tax_rate}% (Tax Exclusive) has been charged separately on the base price. GSTIN: 33BUWPR7004L2ZZ`
                : d.tax_treatment === 'inclusive'
                ? `<strong>GST Note:</strong> Price is tax inclusive. GST @ ${d.tax_rate}% amounting to ${fmt(d.tax_amount)} is included in the total. GSTIN: 33BUWPR7004L2ZZ`
                : `<strong>Note:</strong> No GST applicable on this transaction.`}
        </div>

        <!-- Payment reference -->
        ${d.payment_ref !== 'N/A' ? `
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.8rem;color:#166534;">
            <strong>Payment Reference:</strong> <span style="font-family:monospace;">${esc(d.payment_ref)}</span>
        </div>` : ''}

        <!-- Footer -->
        <div style="border-top:1px solid #e5e7eb;padding-top:14px;text-align:center;">
            <p style="font-size:0.75rem;color:#9ca3af;margin:0 0 3px;">This is a computer-generated invoice and does not require a signature.</p>
            <p style="font-size:0.75rem;color:#9ca3af;margin:0;">Digifyce · GSTIN: 33BUWPR7004L2ZZ · Coimbatore, Tamil Nadu – 641044</p>
        </div>`;

    document.getElementById('invoiceModal').style.display = 'block';
}

function savePDF(btn) {
    if (!_invoiceData) return;
    const el  = document.getElementById('invoiceContent');
    const orig = btn.textContent;
    btn.textContent = 'Generating…';
    btn.disabled = true;
    html2pdf()
        .set({
            margin:     [12, 12, 12, 12],
            filename:   `${_invoiceData.invoice_no}.pdf`,
            image:      { type: 'jpeg', quality: 0.98 },
            html2canvas:{ scale: 2, useCORS: true, logging: false },
            jsPDF:      { unit: 'mm', format: 'a4', orientation: 'portrait' }
        })
        .from(el)
        .save()
        .then(() => {
            btn.textContent = orig;
            btn.disabled = false;
        });
}

function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str||''));
    return d.innerHTML;
}

document.addEventListener('keydown', e => {
    if(e.key==='Enter' && document.getElementById('step1')?.classList.contains('active')) handleSubmit();
});
</script>
</body>
</html>
