<?php
$pageTitle = 'Blog Post | Crm.Zingbot.io';
include_once 'includes/landing-header.php';

$postId = $_GET['id'] ?? 1;

// Content for different posts
$posts = [
    1 => [
        'title' => 'How to maximize your WhatsApp reach with Zingbot',
        'category' => 'Product Updates',
        'date' => 'Oct 12, 2024',
        'author' => 'Zingbot Team',
        'icon' => 'chat_bubble',
        'color' => 'orange',
        'content' => '
            <p class="mb-6 text-lg font-medium text-slate-700">In today\'s digital landscape, reaching your customers where they are is more important than ever. With over 2 billion active users, WhatsApp is the leader in personalized messaging.</p>
            <h2 class="text-3xl font-bold text-slate-900 mb-6">1. Personalization at Scale</h2>
            <p class="mb-6">Zingbot allows you to use dynamic variables in your broadcasts. Instead of "Hello customer," you can say "Hi [First Name], I hope your [Company Name] project is going well!"</p>
            <h2 class="text-3xl font-bold text-slate-900 mb-6">2. Strategic Timing</h2>
            <p class="mb-6">Our analytics show that broadcasts sent between 10 AM and 2 PM local time have the highest open rates. Use our scheduling features to optimize your reach.</p>
        '
    ],
    2 => [
        'title' => '5 Automation workflows every agency needs in 2024',
        'category' => 'Strategy',
        'date' => 'Nov 05, 2024',
        'author' => 'Sarah Johnson',
        'icon' => 'bolt',
        'color' => 'teal',
        'content' => '
            <p class="mb-6 text-lg font-medium text-slate-700">Automation is no longer a luxury; it\'s a necessity for scaling agencies. Here are the top 5 workflows you should implement today.</p>
            <h2 class="text-3xl font-bold text-slate-900 mb-6">1. Lead Qualification Bot</h2>
            <p class="mb-6">Stop wasting time on leads that aren\'t a good fit. Use an automated questionnaire to qualify leads before they ever touch your sales team.</p>
            <h2 class="text-3xl font-bold text-slate-900 mb-6">2. Immediate Response</h2>
            <p class="mb-6">The first agency to respond usually wins the client. Set up an instant WhatsApp auto-reply to confirm receipt and schedule a call.</p>
        '
    ]
];

$post = $posts[$postId] ?? $posts[1];
$bgColor = $post['color'] === 'orange' ? 'bg-orange-100' : 'bg-teal-100';
$iconColor = $post['color'] === 'orange' ? 'text-primary' : 'text-secondary';
?>

<article class="pt-32 pb-32 bg-white">
    <div class="max-w-4xl mx-auto px-4">
        <div class="mb-12">
            <a href="blog.php" class="inline-flex items-center gap-2 text-sm font-bold text-primary hover:gap-3 transition-all mb-8">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                Back to Blog
            </a>
            <span class="text-xs font-black uppercase tracking-widest text-primary mb-4 block"><?php echo $post['category']; ?></span>
            <h1 class="text-4xl md:text-6xl font-black text-slate-900 mb-8 leading-tight">
                <?php echo $post['title']; ?>
            </h1>
            <div class="flex items-center gap-4 text-slate-500 font-medium">
                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-400">person</span>
                </div>
                <div>
                    <p class="text-slate-900 font-bold"><?php echo $post['author']; ?></p>
                    <p class="text-sm"><?php echo $post['date']; ?> • 5 min read</p>
                </div>
            </div>
        </div>

        <div class="aspect-video <?php echo $bgColor; ?> rounded-[3rem] mb-16 overflow-hidden flex items-center justify-center">
             <span class="material-symbols-outlined <?php echo $iconColor; ?> text-primary text-9xl opaicty-20"><?php echo $post['icon']; ?></span>
        </div>

        <div class="prose prose-slate prose-lg max-w-none text-slate-600 leading-relaxed">
            <?php echo $post['content']; ?>
        </div>

        <div class="mt-20 pt-12 border-t border-slate-100">
            <h3 class="text-2xl font-bold mb-8">Ready to start your own <span class="text-primary">success</span> story?</h3>
            <p class="mb-12 text-slate-500">Scale your outreach today with Crm.Zingbot.io. No credit card required, 14-day free trial.</p>
            <a href="register.php" class="inline-block bg-primary hover:bg-orange-600 text-white text-lg font-bold px-12 py-5 rounded-2xl transition-all shadow-2xl shadow-orange-200 active:scale-95">
                Join Crm.Zingbot.io Today
            </a>
        </div>

<?php include_once 'includes/landing-footer.php'; ?>
