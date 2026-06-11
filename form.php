<?php
$pageTitle = 'Contact Us | Crm.Zingbot.io';
include_once 'includes/landing-header.php';
?>

<section class="pt-40 pb-32 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h1 id="form-heading" class="text-5xl font-black text-slate-900 mb-6 tracking-tight">Get in <span class="text-primary italic">touch</span></h1>
            <p class="text-slate-500 font-medium max-w-xl mx-auto text-lg">Have questions about Crm.Zingbot.io? We're here to help you scale your connection.</p>
        </div>
        <div class="bg-slate-50 rounded-[3rem] p-8 md:p-12 border border-slate-100 shadow-2xl shadow-slate-200/50">
            <form id="contact-form" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Hidden plan tag -->
                <input type="hidden" name="plan_tag" id="plan_tag" value="">

                <div class="space-y-2">
                    <label class="text-sm font-bold text-slate-700 uppercase tracking-wider ml-2">Full Name</label>
                    <input type="text" name="name" required placeholder="John Doe" class="w-full px-6 py-4 rounded-2xl border-2 border-white focus:border-primary focus:ring-0 transition-all outline-none bg-white shadow-sm">
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-bold text-slate-700 uppercase tracking-wider ml-2">Email Address</label>
                    <input type="email" name="email" required placeholder="john@agency.com" class="w-full px-6 py-4 rounded-2xl border-2 border-white focus:border-primary focus:ring-0 transition-all outline-none bg-white shadow-sm">
                </div>
                <div class="md:col-span-2 space-y-2">
                    <label class="text-sm font-bold text-slate-700 uppercase tracking-wider ml-2">Organization</label>
                    <input type="text" name="org" placeholder="Your Agency Name" class="w-full px-6 py-4 rounded-2xl border-2 border-white focus:border-primary focus:ring-0 transition-all outline-none bg-white shadow-sm">
                </div>
                <div class="md:col-span-2 space-y-2">
                    <label class="text-sm font-bold text-slate-700 uppercase tracking-wider ml-2">Message</label>
                    <textarea name="message" id="message-field" required rows="5" placeholder="How can we help you?" class="w-full px-6 py-4 rounded-2xl border-2 border-white focus:border-primary focus:ring-0 transition-all outline-none bg-white shadow-sm resize-none"></textarea>
                </div>
                <div class="md:col-span-2 pt-4 text-center">
                    <button type="submit" id="submit-btn" class="w-full bg-primary hover:bg-orange-600 text-white font-black text-xl py-6 rounded-2xl transition-all shadow-xl shadow-orange-200 active:scale-[0.98]">
                        Send Message
                    </button>
                    <div id="form-message" class="mt-4 font-bold text-slate-600 hidden"></div>
                </div>
            </form>

            <script>
                // Detect ?plan=enterprise in URL and customize the form
                (function() {
                    const params = new URLSearchParams(window.location.search);
                    const plan = params.get('plan');
                    if (plan === 'enterprise') {
                        // Show enterprise banner
                        const banner = document.getElementById('enterprise-banner');
                        if (banner) banner.classList.remove('hidden');

                        // Set hidden plan tag
                        document.getElementById('plan_tag').value = 'Enterprise Plan';

                        // Pre-fill message
                        const msgField = document.getElementById('message-field');
                        if (msgField && !msgField.value) {
                            msgField.value = "Hi, I'm interested in the Enterprise Plan. I'd like to learn more about custom pricing and features for my team.";
                        }

                        // Update page heading
                        const heading = document.getElementById('form-heading');
                        if (heading) heading.innerHTML = 'Enterprise Plan <span class="text-primary italic">Enquiry</span>';
                    }
                })();

                document.getElementById('contact-form').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const form = this;
                    const btn = document.getElementById('submit-btn');
                    const msgDiv = document.getElementById('form-message');
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());

                    btn.disabled = true;
                    btn.innerText = 'Sending...';
                    msgDiv.classList.add('hidden');

                    try {
                        const response = await fetch('api/contact/submit.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(data)
                        });
                        const result = await response.json();
                        
                        msgDiv.classList.remove('hidden');
                        if (result.success) {
                            msgDiv.innerText = result.message;
                            msgDiv.className = 'mt-4 font-bold text-emerald-500 bg-emerald-50 py-3 rounded-xl';
                            form.reset();
                        } else {
                            msgDiv.innerText = result.message || 'Something went wrong.';
                            msgDiv.className = 'mt-4 font-bold text-red-500 bg-red-50 py-3 rounded-xl';
                        }
                    } catch (error) {
                        msgDiv.classList.remove('hidden');
                        msgDiv.innerText = 'Network error. Please try again later.';
                        msgDiv.className = 'mt-4 font-bold text-red-500 bg-red-50 py-3 rounded-xl';
                    } finally {
                        btn.disabled = false;
                        btn.innerText = 'Send Message';
                    }
                });
            </script>
        </div>
    </div>
</section>

<?php include_once 'includes/landing-footer.php'; ?>
