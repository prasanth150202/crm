<?php
$pageTitle = 'Refund & Cancellation Policy | Crm.Zingbot.io';
include_once 'includes/landing-header.php';
?>

<section class="pt-40 pb-24 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-14">
            <span class="inline-flex items-center gap-2 bg-orange-50 text-primary text-xs font-black uppercase tracking-widest px-4 py-2 rounded-full mb-6">
                Legal
            </span>
            <h1 class="text-5xl font-black text-slate-900 tracking-tight mb-4">Refund & <span class="text-primary italic">Cancellation</span> Policy</h1>
            <p class="text-slate-400 font-semibold">Last Updated: <span class="text-slate-600">20 February 2026</span></p>
            <div class="mt-6 p-5 bg-amber-50 border border-amber-200 rounded-2xl text-sm text-amber-800 font-semibold">
                By subscribing to our services, you agree to this Refund & Cancellation Policy.
            </div>
        </div>

        <!-- Intro -->
        <div class="mb-10 text-slate-600 leading-relaxed text-base">
            <p>This Refund &amp; Cancellation Policy explains how subscriptions, free trials, cancellations, and refunds are handled for the Zingbot CRM Application.</p>
        </div>

        <!-- Key Highlights Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-14">
            <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-6 text-center">
                <span class="text-3xl">🎁</span>
                <p class="font-black text-slate-900 mt-3 mb-1">7-Day Free Trial</p>
                <p class="text-sm text-slate-500">Try all features free before paying</p>
            </div>
            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-6 text-center">
                <span class="text-3xl">🔄</span>
                <p class="font-black text-slate-900 mt-3 mb-1">Cancel Anytime</p>
                <p class="text-sm text-slate-500">No lock-in, stop future renewals instantly</p>
            </div>
            <div class="bg-red-50 border border-red-100 rounded-2xl p-6 text-center">
                <span class="text-3xl">🚫</span>
                <p class="font-black text-slate-900 mt-3 mb-1">No Refunds</p>
                <p class="text-sm text-slate-500">All payments are non-refundable</p>
            </div>
        </div>

        <!-- Sections -->
        <div class="space-y-10">
            <?php
            $sections = [
                [
                    'num' => '1',
                    'icon' => '🎁',
                    'title' => '7-Day Free Trial',
                    'items' => [
                        'Zingbot CRM Application offers a 7-day free trial to new users.',
                        'During the free trial period, users can access available features without being charged.',
                        'At the end of the 7-day trial, the selected subscription plan will automatically convert into a paid monthly subscription unless cancelled before the trial ends.',
                        'It is the user\'s responsibility to cancel before the trial period ends to avoid charges.',
                    ],
                ],
                [
                    'num' => '2',
                    'icon' => '💳',
                    'title' => 'Subscription & Billing',
                    'items' => [
                        'After the free trial, the applicable monthly subscription fee will be automatically charged.',
                        'Subscription fees are billed in advance.',
                        'By subscribing, you authorize us to charge the selected payment method on a recurring monthly basis.',
                    ],
                ],
                [
                    'num' => '3',
                    'icon' => '🔄',
                    'title' => 'Cancellation Policy',
                    'items' => [
                        'Users may cancel their subscription at any time directly within the Application.',
                        'Cancellation will stop future renewals.',
                        'However, cancellation does not immediately terminate access to the service.',
                        'The subscription will remain active and accessible until the end of the current billing cycle.',
                    ],
                ],
                [
                    'num' => '4',
                    'icon' => '🚫',
                    'title' => 'No Refund Policy',
                    'highlight' => 'All subscription payments are non-refundable.',
                    'intro' => 'Once a payment has been processed, no refunds will be issued for:',
                    'items' => [
                        'Partial use of the subscription period',
                        'Unused features',
                        'Early cancellation',
                        'Accidental purchase',
                    ],
                    'footer' => 'Even if you cancel your subscription, your plan will remain active until the subscription period expires, and no refund will be provided for the remaining time.',
                ],
                [
                    'num' => '5',
                    'icon' => '🗄️',
                    'title' => 'Data Retention After Cancellation',
                    'items' => [
                        'Upon cancellation, your data will remain accessible until the subscription period ends.',
                        'After the subscription expires, data will be retained for up to three (3) months.',
                        'If no reactivation occurs within this period, all data will be permanently deleted from our servers.',
                    ],
                ],
                [
                    'num' => '6',
                    'icon' => '⚠️',
                    'title' => 'Exceptional Circumstances',
                    'intro' => 'Refunds may only be considered in rare cases of:',
                    'items' => [
                        'Duplicate payments',
                        'Proven technical billing errors',
                    ],
                    'footer' => 'All such requests must be submitted within 7 days of the charge. Approval of any exception is at the sole discretion of Zingbot CRM Application.',
                ],
                [
                    'num' => '7',
                    'icon' => '📝',
                    'title' => 'Changes to This Policy',
                    'intro' => 'We reserve the right to update or modify this Refund & Cancellation Policy at any time. Continued use of the Application constitutes acceptance of the updated policy.',
                ],
            ];

            foreach ($sections as $s): ?>
                <div>
                    <div class="flex gap-5 items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-center text-2xl">
                            <?= $s['icon'] ?>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="text-xs font-black text-primary bg-primary/10 px-2 py-0.5 rounded-full"><?= $s['num'] ?></span>
                                <h2 class="text-xl font-black text-slate-900"><?= $s['title'] ?></h2>
                            </div>

                            <?php if (!empty($s['highlight'])): ?>
                                <div class="mb-3 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 font-bold text-sm">
                                    ⚠️ <?= $s['highlight'] ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($s['intro'])): ?>
                                <p class="text-slate-600 mb-3"><?= $s['intro'] ?></p>
                            <?php endif; ?>

                            <?php if (!empty($s['items'])): ?>
                                <ul class="space-y-2 mb-3">
                                    <?php foreach ($s['items'] as $item): ?>
                                        <li class="flex items-start gap-2 text-slate-600">
                                            <span class="mt-2 w-1.5 h-1.5 rounded-full bg-primary flex-shrink-0"></span>
                                            <?= $item ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($s['footer'])): ?>
                                <p class="text-slate-600 text-sm mt-2"><?= $s['footer'] ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="border-b border-slate-100 mt-8"></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Contact & Links -->
        <div class="mt-16 bg-slate-50 rounded-3xl p-8 text-center border border-slate-100">
            <p class="font-black text-slate-900 text-lg mb-2">Have a billing question?</p>
            <p class="text-slate-500 text-sm mb-5">Reach out to our support team and we'll get back to you.</p>
            <a href="mailto:support@zingbot.io" class="inline-flex items-center gap-2 bg-primary text-white font-black px-8 py-4 rounded-2xl hover:bg-orange-600 transition-all shadow-lg shadow-orange-200">
                support@zingbot.io
            </a>
            <div class="mt-8 pt-6 border-t border-slate-200">
                <a href="terms.php" class="text-sm text-slate-400 hover:text-primary font-semibold transition-colors">← View Terms & Conditions</a>
            </div>
        </div>

    </div>
</section>

<?php include_once 'includes/landing-footer.php'; ?>
