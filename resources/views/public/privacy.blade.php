@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto py-8 text-start space-y-8">
    <header class="border-b border-slate-200 pb-6">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-3">
            {{ app()->getLocale() === 'ar' ? 'سياسة الخصوصية وحماية البيانات' : 'Privacy Policy & Data Protection' }}
        </h1>
        <p class="text-slate-600 text-sm leading-relaxed">
            {{ app()->getLocale() === 'ar' 
                ? 'وثيقة الامتثال القانوني وسرية الزمالة وفقاً لقانون حماية البيانات الشخصية المصري رقم 151 لسنة 2020 وتقاليد زمالة مدمني الخمر المجهولين.' 
                : 'Statutory compliance and fellowship confidentiality statement in accordance with Egyptian Personal Data Protection Law No. 151 of 2020 and the Traditions of Alcoholics Anonymous.' }}
        </p>
    </header>

    @if(app()->getLocale() === 'ar')
        <!-- Introduction & Overview (Arabic) -->
        <section class="prose max-w-none text-slate-700 leading-relaxed space-y-4">
            <h2 class="text-2xl font-bold text-slate-900">مقدمة والتزام الخصوصية</h2>
            <p>
                تلتزم زمالة مدمني الخمر المجهولين في مصر (A.A. Egypt) بأعلى معايير حماية الخصوصية والسرية الفردية. تنطلق هذه السياسة من التزام مزدوج: 
                <strong>الالتزام القانوني</strong> بأحكام التشريعات المصرية لحماية البيانات، و<strong>الالتزام الروحي</strong> بتقاليد الزمالة الاثني عشر التي تؤكد أن السرية والتخفي هما حجر الأساس للتعافي.
            </p>
            <div class="p-4 bg-emerald-50 border-s-4 border-emerald-600 rounded-e-md text-emerald-950 text-sm space-y-2">
                <p class="font-semibold">المبادئ الجوهرية لمنظومة الخصوصية:</p>
                <ul class="list-disc ps-5 space-y-1">
                    <li><strong>انعدام ملفات تعريف الارتباط بالكامل (Zero Cookies):</strong> لا تُصدر صفحات موقعنا العامة أي ملفات تعريف ارتباط (كوكيز) للمتصفح مطلقاً.</li>
                    <li><strong>انعدام التتبع الخارجي (Zero Third-Party Tracking):</strong> لا نستخدم أي أدوات تحليلات خارجية أو شبكات إعلانية أو خطوط مستضافة خارجياً أو وسائط مضمنة من أطراف ثالثة.</li>
                    <li><strong>انعدام ترويسات التتبع:</strong> الزائر الذي يتصفح الموقع دون تسجيل الدخول إلى بوابة الخدمة لا يتلقى أي ترويسات تتبع أو تعريف بهويته.</li>
                </ul>
            </div>
        </section>

        <!-- Appendix A: Egyptian Data Protection Law Disclosures (Arabic) -->
        <section class="border-t border-slate-200 pt-6 space-y-4">
            <h2 class="text-2xl font-bold text-slate-900 text-emerald-900">
                الملحق (أ): إفصاحات حماية البيانات القانونية (قانون حماية البيانات الشخصية المصري رقم 151 لسنة 2020)
            </h2>
            <div class="prose max-w-none text-slate-700 leading-relaxed space-y-4 text-sm sm:text-base">
                <h3 class="text-lg font-semibold text-slate-900">1. السند التشريعي ونطاق التطبيق</h3>
                <p>
                    تخضع هذه الإفصاحات لأحكام <strong>قانون حماية البيانات الشخصية المصري رقم 151 لسنة 2020</strong> ولائحته التنفيذية. يهدف هذا الإفصاح إلى توضيح كيفية تعامل الموقع مع البيانات وحقوق الأشخاص المعنيين بالبيانات في جمهورية مصر العربية.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">2. بيانات المتحكم في البيانات (Data Controller)</h3>
                <p>
                    <strong>المتحكم في البيانات:</strong> زمالة مدمني الخمر المجهولين في جمهورية مصر العربية (A.A. Egypt General Service).<br>
                    <strong>قناة التواصل لمسؤول حماية البيانات والخصوصية:</strong> <a href="mailto:privacy@aaegypt.org" class="text-emerald-700 underline">privacy@aaegypt.org</a> أو <a href="mailto:admin@aaegypt.org" class="text-emerald-700 underline">admin@aaegypt.org</a>.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">3. انعدام جمع البيانات الشخصية لزوار الصفحات العامة</h3>
                <p>
                    لا يقوم هذا الموقع بجمع أو معالجة أو تخزين أي بيانات شخصية تخص زوار الصفحات العامة (<code class="bg-slate-100 px-1 rounded">/</code>, <code class="bg-slate-100 px-1 rounded">/ar</code>, <code class="bg-slate-100 px-1 rounded">/en</code>, <code class="bg-slate-100 px-1 rounded">/meetings</code>, <code class="bg-slate-100 px-1 rounded">/privacy</code>, <code class="bg-slate-100 px-1 rounded">/who-we-are</code>). 
                    الموقع يعمل بنظام المعالجة عديمة الحالة (Stateless)، حيث يتم تجريد ترويسات <code class="bg-slate-100 px-1 rounded">Set-Cookie</code> بالكامل، ولا يتم تسجيل عناوين بروتوكول الإنترنت (IP Addresses) لغرض ربطها بهوية الأشخاص أو تتبع سلوك التصفح.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">4. حقوق المعني بالبيانات بموجب القانون رقم 151 لسنة 2020</h3>
                <p>وفقاً لأحكام المواد المقررة بقانون حماية البيانات الشخصية المصري رقم 151 لسنة 2020، يتمتع كل شخص معني بالبيانات بالحقوق التالية:</p>
                <ul class="list-disc ps-6 space-y-1">
                    <li><strong>حق العلم والاطلاع:</strong> معرفة ماهية البيانات التي تُعالج والهدف من معالجتها.</li>
                    <li><strong>حق الرجوع والاعتراض:</strong> الرجوع في الموافقة على المعالجة والاعتراض على أي معالجة غير متوافقة مع القانون.</li>
                    <li><strong>حق التصحيح والتعديل:</strong> طلب تصحيح أي بيانات غير دقيقة أو استكمالها وتحديثها فوراً.</li>
                    <li><strong>حق المحو والحذف (حق النسيان):</strong> طلب محو أو مسح البيانات الشخصية عند انتهاء الغرض منها.</li>
                    <li><strong>حق تقييد المعالجة:</strong> تقييد أو إيقاف معالجة البيانات الشخصية في الأحوال القانونية المقررة.</li>
                    <li><strong>حق الإخطار بالأخطار والخرق:</strong> الإخطار الفوري في حال حدوث أي تسريب أو اختراق أمني للبيانات الشخصية خلال المدد المحددة قانوناً.</li>
                </ul>

                <h3 class="text-lg font-semibold text-slate-900">5. الجهة الرقابية المختصة وحق الشكوى</h3>
                <p>
                    يحق للمعني بالبيانات في جمهورية مصر العربية التقدم بأي شكوى أو بلاغ إلى <strong>«مركز حماية البيانات الشخصية»</strong> المنشأ بموجب أحكام القانون رقم 151 لسنة 2020، وذلك في حال مخالفة أي من الضوابط والمعايير المنصوص عليها قانوناً.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">6. نقل البيانات عبر الحدود</h3>
                <p>
                    يلتزم الموقع بالضوابط المشددة المنصوص عليها في المادة (14) من القانون رقم 151 لسنة 2020 بشأن نقل البيانات الشخصية عبر الحدود، حيث لا يتم تخزين أو نقل أي بيانات شخصية إلى خارج جمهورية مصر العربية إلا وفقاً للتراخيص والضوابط المعتمدة قانوناً، وبعد التأكد من توفير مستوى حماية لا يقل عن المستوى المقرر في القانون المصري.
                </p>
            </div>
        </section>

        <!-- Appendix B: Fellowship Privacy & Traditions (Arabic) -->
        <section class="border-t border-slate-200 pt-6 space-y-4">
            <h2 class="text-2xl font-bold text-slate-900 text-emerald-900">
                الملحق (ب): مبادئ خصوصية وسرية الزمالة (تقاليد زمالة مدمني الخمر المجهولين)
            </h2>
            <div class="prose max-w-none text-slate-700 leading-relaxed space-y-4 text-sm sm:text-base">
                <h3 class="text-lg font-semibold text-slate-900">1. الأساس الروحي للتخفي والسرية (التقليد الثاني عشر)</h3>
                <blockquote class="italic border-s-4 border-emerald-500 ps-4 text-slate-800">
                    «التخفي والسرية هو الأساس الروحي لجميع تقاليدنا، يذكرنا دائماً بتقديم المبادئ على الشخصيات.»
                </blockquote>
                <p>
                    السرية ليست مجرد إجراء تقني أو التزام قانوني في زمالتنا، بل هي جوهر روحي أساسي يضمن الأمان الكامل لكل من يسعى إلى التحرر من إدمان الخمر. نضمن حماية هوية الأعضاء وعدم إفشاء أسماء الحاضرين أو الباحثين عن التعافي.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">2. سياسة العلاقات العامة والإعلام الرقمي (التقليد الحادي عشر)</h3>
                <blockquote class="italic border-s-4 border-emerald-500 ps-4 text-slate-800">
                    «تقوم سياسة علاقاتنا العامة على الجذب لا الترويج، وعلينا دائماً الحفاظ على سرية وتخفي هويتنا الشخصية على مستوى الصحافة والإذاعة والأفلام والإنترنت والوسائط الرقمية.»
                </blockquote>
                <p>
                    امتثالاً لهذا التقليد، يمتنع موقع زمالة مدمني الخمر المجهولين في مصر عن استخدام أي أساليب دعائية أو ترويجية أو أدوات تتبع سلوكي. لا نربط الموقع بشبكات التواصل الاجتماعي أو منصات الإعلانات أو تقنيات إعادة الاستهداف التجاري.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">3. الاستقلالية وعدم التبعية (التقليد السادس)</h3>
                <p>
                    وفقاً للتقليد السادس، لا ترتبط الزمالة بأي مؤسسة أو جهة خارجية، ولا تقبل أي تمويل خارجي. وعليه، فإن منصتنا الرقمية مستقلة تماماً وخالية من أدوات الطرف الثالث التي قد تجمع بيانات المستخدمين لأغراض تجارية.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">4. بوابة الخدمة والحسابات الوظيفية</h3>
                <p>
                    تقتصر الحسابات المصرح لها بتسجيل الدخول إلى بوابة الخدمة على ممثلي خدمة المجموعات (GSR) وأعضاء اللجان الخدمية. تُستخدم عناوين وظيفية رسمية فقط (مثل <code class="bg-slate-100 px-1 rounded">gsr.group@aaegypt.org</code>)، مع تفعيل المصادقة متعددة العوامل (MFA/TOTP) وتدوير كلمات المرور، لحماية العمل الخدمي والحفاظ على سرية الأعضاء.
                </p>
            </div>
        </section>
    @else
        <!-- Introduction & Overview (English) -->
        <section class="prose max-w-none text-slate-700 leading-relaxed space-y-4">
            <h2 class="text-2xl font-bold text-slate-900">Introduction & Privacy Commitment</h2>
            <p>
                The Fellowship of Alcoholics Anonymous in Egypt (A.A. Egypt) is dedicated to safeguarding individual privacy and personal anonymity. Our policy is grounded in a dual commitment:
                <strong>Statutory Compliance</strong> with Egyptian data protection legislation, and <strong>Spiritual Fidelity</strong> to the Twelve Traditions of Alcoholics Anonymous, which declare that personal anonymity is the protective foundation of recovery.
            </p>
            <div class="p-4 bg-emerald-50 border-s-4 border-emerald-600 rounded-e-md text-emerald-950 text-sm space-y-2">
                <p class="font-semibold">Core Architectural Privacy Principles:</p>
                <ul class="list-disc ps-5 space-y-1">
                    <li><strong>Strict Zero-Cookie Architecture:</strong> Our public web pages set ZERO cookies in visitors' browsers under all circumstances.</li>
                    <li><strong>Zero Third-Party Tracking:</strong> We load no third-party analytics, ad trackers, third-party CDNs, external Google fonts, or embedded external players.</li>
                    <li><strong>Zero Tracking Headers:</strong> Visitors browsing public resources without logging into the internal service portal receive zero tracking headers or session tokens.</li>
                </ul>
            </div>
        </section>

        <!-- Appendix A: Egyptian Data Protection Law Disclosures (English) -->
        <section class="border-t border-slate-200 pt-6 space-y-4">
            <h2 class="text-2xl font-bold text-slate-900 text-emerald-900">
                Appendix A: Statutory Privacy Disclosures (Egyptian Personal Data Protection Law No. 151 of 2020)
            </h2>
            <div class="prose max-w-none text-slate-700 leading-relaxed space-y-4 text-sm sm:text-base">
                <h3 class="text-lg font-semibold text-slate-900">1. Statutory Framework & Scope</h3>
                <p>
                    These statutory disclosures are rendered in strict compliance with the provisions of <strong>Egyptian Personal Data Protection Law No. 151 of 2020</strong> and its executive regulations. This notice informs public visitors and data subjects within the Arab Republic of Egypt regarding our data processing policies.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">2. Data Controller Identity & Contact</h3>
                <p>
                    <strong>Data Controller:</strong> General Service Board / Fellowship of Alcoholics Anonymous in Egypt (A.A. Egypt).<br>
                    <strong>Privacy & Compliance Contact:</strong> <a href="mailto:privacy@aaegypt.org" class="text-emerald-700 underline">privacy@aaegypt.org</a> or <a href="mailto:admin@aaegypt.org" class="text-emerald-700 underline">admin@aaegypt.org</a>.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">3. Non-Collection of Personal Data from Public Visitors</h3>
                <p>
                    This platform does not collect, record, process, or store any personal data regarding visitors to its public pages (<code class="bg-slate-100 px-1 rounded">/</code>, <code class="bg-slate-100 px-1 rounded">/ar</code>, <code class="bg-slate-100 px-1 rounded">/en</code>, <code class="bg-slate-100 px-1 rounded">/meetings</code>, <code class="bg-slate-100 px-1 rounded">/privacy</code>, <code class="bg-slate-100 px-1 rounded">/who-we-are</code>). 
                    Public browsing is stateless: <code class="bg-slate-100 px-1 rounded">Set-Cookie</code> headers are stripped entirely, and IP addresses are never utilized to establish visitor identity, profile habits, or trace fellowship inquiries.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">4. Data Subject Rights under Egyptian Law No. 151 of 2020</h3>
                <p>In accordance with Egyptian Personal Data Protection Law No. 151 of 2020, data subjects are guaranteed the following statutory rights:</p>
                <ul class="list-disc ps-6 space-y-1">
                    <li><strong>Right to Know & Access:</strong> The right to be informed of data handling policies and review any personal data held.</li>
                    <li><strong>Right to Revoke & Object:</strong> The right to revoke consent and object to unlawful or unauthorized data processing.</li>
                    <li><strong>Right to Rectification:</strong> The right to request the correction, amendment, and updating of inaccurate data without delay.</li>
                    <li><strong>Right to Erasure (Oblivion):</strong> The right to request the complete deletion and erasure of personal data once processing purposes terminate.</li>
                    <li><strong>Right to Restriction:</strong> The right to limit or suspend processing operations in accordance with statutory conditions.</li>
                    <li><strong>Right to Breach Notification:</strong> The right to receive immediate notification of any security breach or data infringement within statutory deadlines.</li>
                </ul>

                <h3 class="text-lg font-semibold text-slate-900">5. Supervisory Authority & Complaints</h3>
                <p>
                    Under Egyptian Law No. 151 of 2020, data subjects have the right to submit complaints and grievances to the <strong>Egyptian Personal Data Protection Center</strong> (مركز حماية البيانات الشخصية) regarding any perceived violation of their personal data rights.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">6. Cross-Border Data Transfers</h3>
                <p>
                    We strictly observe the statutory requirements of Article 14 et seq. of Law No. 151 of 2020. No personal data is transferred across Egyptian borders without requisite licenses, permits, and verification that recipient territories maintain data protection safeguards equivalent to Egyptian law.
                </p>
            </div>
        </section>

        <!-- Appendix B: Fellowship Privacy & Traditions (English) -->
        <section class="border-t border-slate-200 pt-6 space-y-4">
            <h2 class="text-2xl font-bold text-slate-900 text-emerald-900">
                Appendix B: Fellowship Privacy and Anonymity Principles (Twelve Traditions of Alcoholics Anonymous)
            </h2>
            <div class="prose max-w-none text-slate-700 leading-relaxed space-y-4 text-sm sm:text-base">
                <h3 class="text-lg font-semibold text-slate-900">1. Spiritual Foundation of Anonymity (Tradition Twelve)</h3>
                <blockquote class="italic border-s-4 border-emerald-500 ps-4 text-slate-800">
                    "Anonymity is the spiritual foundation of all our Traditions, ever reminding us to place principles before personalities."
                </blockquote>
                <p>
                    In Alcoholics Anonymous, anonymity is not merely a technical precaution; it is a vital spiritual safeguard that ensures every individual seeking recovery may find unconditional confidentiality and safety within the fellowship.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">2. Public Relations & Digital Media (Tradition Eleven)</h3>
                <blockquote class="italic border-s-4 border-emerald-500 ps-4 text-slate-800">
                    "Our public relations policy is based on attraction rather than promotion; we need always maintain personal anonymity at the level of press, radio, films, and the internet."
                </blockquote>
                <p>
                    In upholding Tradition Eleven, A.A. Egypt operates without advertising, self-promotion, or algorithmic profiling. We do not integrate tracking beacons, remarketing pixels, or third-party social media trackers into our digital platforms.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">3. Non-Affiliation and Independence (Tradition Six)</h3>
                <p>
                    In accordance with Tradition Six, our fellowship never endorses, finances, or lends our name to any outside enterprise. Consequently, our web services operate independently from commercial data networks and ad exchanges.
                </p>

                <h3 class="text-lg font-semibold text-slate-900">4. Internal Service Portal Security</h3>
                <p>
                    Access to our service management portal is restricted to trusted fellowship servants (Group Service Representatives, Delegates, and Committee Coordinators). Accounts utilize functional role addresses (e.g. <code class="bg-slate-100 px-1 rounded">gsr.group@aaegypt.org</code>), reinforced with mandatory Time-based One-Time Password (TOTP/MFA) multi-factor authentication, ensuring that fellowship management maintains complete confidentiality.
                </p>
            </div>
        </section>
    @endif
</div>
@endsection
