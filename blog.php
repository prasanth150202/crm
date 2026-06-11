<?php
$pageTitle = 'Blog | Crm.Zingbot.io';
include_once 'includes/landing-header.php';
?>

<section class="pt-32 pb-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-5xl font-black text-slate-900 mb-8 tracking-tight">
            Latest from the <span class="text-secondary italic">Crm.Zingbot.io</span> Lab
        </h1>
    </div>
</section>

<section class="pb-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-12">
            <!-- Blog Card 1 -->
            <a href="blog-single.php?id=1" class="group cursor-pointer">
                <div class="aspect-video bg-orange-100 rounded-[2rem] mb-6 overflow-hidden">
                    <div class="w-full h-full bg-primary/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-500">
                        <span class="material-symbols-outlined text-primary text-6xl">chat_bubble</span>
                    </div>
                </div>
                <div class="px-2">
                    <span class="text-xs font-black uppercase tracking-widest text-primary mb-3 block">Product Updates</span>
                    <h3 class="text-2xl font-bold text-slate-900 mb-4 group-hover:text-primary transition-colors">How to maximize your WhatsApp reach with Zingbot</h3>
                    <p class="text-slate-500 line-clamp-2">Discover the best practices for sending broadcasts that convert without sounding like a bot.</p>
                </div>
            </a>
            
            <!-- Blog Card 2 -->
            <a href="blog-single.php?id=2" class="group cursor-pointer">
                <div class="aspect-video bg-teal-100 rounded-[2rem] mb-6 overflow-hidden">
                    <div class="w-full h-full bg-secondary/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-500">
                        <span class="material-symbols-outlined text-secondary text-6xl">bolt</span>
                    </div>
                </div>
                <div class="px-2">
                    <span class="text-xs font-black uppercase tracking-widest text-secondary mb-3 block">Strategy</span>
                    <h3 class="text-2xl font-bold text-slate-900 mb-4 group-hover:text-secondary transition-colors">5 Automation workflows every agency needs in 2024</h3>
                    <p class="text-slate-500 line-clamp-2">Save hours of manual work by implementing these simple yet powerful automations today.</p>
                </div>
            </a>
        </div>
    </div>
</section>

<?php include_once 'includes/landing-footer.php'; ?>
