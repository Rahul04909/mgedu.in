<!-- Link to Blogs Stylesheet -->
<link rel="stylesheet" href="assets/css/blogs.css">

<section class="blogs-section">
    <div class="blogs-container">
        
        <!-- Headers Section -->
        <div class="blogs-header">
            <h2>MG Knowledge Hub &amp; Insights</h2>
            <p>Stay updated with the latest in technology, learning strategies, career pathways, and industry trends.</p>
        </div>

        <!-- Filter Tab Buttons -->
        <div class="blogs-filter-wrapper">
            <div class="blogs-filter-tabs">
                <button class="blog-filter-tab active" id="tab-blog-tech" onclick="filterBlogs('tech')">Tech Trends</button>
                <button class="blog-filter-tab" id="tab-blog-career" onclick="filterBlogs('career')">Career Advice</button>
                <button class="blog-filter-tab" id="tab-blog-study" onclick="filterBlogs('study')">Study Guides</button>
                <button class="blog-filter-tab" id="tab-blog-design" onclick="filterBlogs('design')">Design Insights</button>
                <button class="blog-filter-tab" id="tab-blog-biz" onclick="filterBlogs('business')">Business Growth</button>
            </div>
        </div>

        <!-- Slider Grid Wrapper -->
        <div class="blogs-grid-container">
            
            <!-- Slider Arrows (Udemy / PW style) -->
            <button class="blogs-nav-btn prev-btn" id="blogPrevBtn" onclick="slideBlogGrid('prev')" aria-label="Previous Blogs">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            
            <button class="blogs-nav-btn next-btn" id="blogNextBtn" onclick="slideBlogGrid('next')" aria-label="Next Blogs">
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            <!-- Blog Cards Grid -->
            <div class="blogs-grid" id="blogsGrid" onscroll="updateBlogNavButtons()">
                
                <!-- ================= TECH TRENDS ================= -->
                
                <!-- Card 1 -->
                <a href="#" class="blog-card" data-category="tech">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=400&q=80" alt="Generative AI Trends">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">The Rise of AI Agents: Transforming Software Engineering</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 20, 2026</span> • <span class="meta-read">8 min read</span></p>
                        <p class="blog-excerpt">Explore how autonomous AI agents are changing coding workflows, developer productivity, and the future skillset...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Dr. Priya Sharma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Tech Trends</span>
                        </div>
                    </div>
                </a>

                <!-- Card 2 -->
                <a href="#" class="blog-card" data-category="tech">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=400&q=80" alt="Cybersecurity Basics">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Introduction to Cybersecurity: Protecting Modern Web Apps</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 12, 2026</span> • <span class="meta-read">7 min read</span></p>
                        <p class="blog-excerpt">Explore the top OWASP web vulnerabilities and get simple actionable advice on how to secure endpoints...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Neha Joshi</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Tech Trends</span>
                        </div>
                    </div>
                </a>

                <!-- Card 3 -->
                <a href="#" class="blog-card" data-category="tech">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=400&q=80" alt="Cloud Computing">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Why Serverless Architecture is the Future of Scalability</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 01, 2026</span> • <span class="meta-read">6 min read</span></p>
                        <p class="blog-excerpt">Understand the pricing models, benefits, and development cycles when migrating to AWS Lambda...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Rahul Kapoor</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Tech Trends</span>
                        </div>
                    </div>
                </a>

                <!-- Card 4 -->
                <a href="#" class="blog-card" data-category="tech">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1535378917042-10a22c95931a?auto=format&fit=crop&w=400&q=80" alt="AI in Education">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">How EdTech Platforms Leverage AI for Personalized Learning</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 25, 2026</span> • <span class="meta-read">5 min read</span></p>
                        <p class="blog-excerpt">A look into how ML algorithms evaluate student performance gaps to deliver customized curriculum...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Dr. Priya Sharma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Tech Trends</span>
                        </div>
                    </div>
                </a>

                <!-- Card 5 -->
                <a href="#" class="blog-card" data-category="tech">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&w=400&q=80" alt="Clean Code">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Writing Clean Code: Best Practices for Junior Developers</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 05, 2026</span> • <span class="meta-read">6 min read</span></p>
                        <p class="blog-excerpt">Learn why meaningful variable naming and modular functions make your code maintainable...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Rahul Kapoor</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Tech Trends</span>
                        </div>
                    </div>
                </a>

                <!-- Card 6 -->
                <a href="#" class="blog-card" data-category="tech">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=400&q=80" alt="Web3 Trends">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Demystifying Web3: Blockchain Basics for Web Developers</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 15, 2026</span> • <span class="meta-read">9 min read</span></p>
                        <p class="blog-excerpt">Get a simplified introduction to Smart Contracts, decentralized storage, and Web3.js integrations...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Amit Verma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Tech Trends</span>
                        </div>
                    </div>
                </a>

                <!-- ================= CAREER ADVICE ================= -->
                
                <!-- Career Card 1 -->
                <a href="#" class="blog-card hidden" data-category="career">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1486312338219-ce68d2c6f44d?auto=format&fit=crop&w=400&q=80" alt="Tech Internships Guide">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">How to Land Your First Software Engineering Internship</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 18, 2026</span> • <span class="meta-read">5 min read</span></p>
                        <p class="blog-excerpt">Learn the exact resume formats, project portfolios, and interview strategies that set you apart...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Rahul Kapoor</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Career Advice</span>
                        </div>
                    </div>
                </a>

                <!-- Career Card 2 -->
                <a href="#" class="blog-card hidden" data-category="career">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=400&q=80" alt="Remote Work Tips">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Top Collaboration Tools for Remote Tech Teams</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 10, 2026</span> • <span class="meta-read">4 min read</span></p>
                        <p class="blog-excerpt">Discover how tools like Slack, Figma, and GitHub Projects help developers collaborate seamlessly...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Sara Davis</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Career Advice</span>
                        </div>
                    </div>
                </a>

                <!-- Career Card 3 -->
                <a href="#" class="blog-card hidden" data-category="career">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=400&q=80" alt="Interview Prep">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Mastering the Tech Interview: Coding &amp; Systems Design</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 28, 2026</span> • <span class="meta-read">8 min read</span></p>
                        <p class="blog-excerpt">A breakdown of what senior engineers look for in live coding rounds and behavioral sessions...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Neha Joshi</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Career Advice</span>
                        </div>
                    </div>
                </a>

                <!-- Career Card 4 -->
                <a href="#" class="blog-card hidden" data-category="career">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=400&q=80" alt="Freelance Developer">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Guide to Building a Profitable Tech Freelance Career</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 10, 2026</span> • <span class="meta-read">6 min read</span></p>
                        <p class="blog-excerpt">How to set your pricing rates, write proposals, and retain international clients effectively...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Amit Verma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Career Advice</span>
                        </div>
                    </div>
                </a>

                <!-- Career Card 5 -->
                <a href="#" class="blog-card hidden" data-category="career">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=400&q=80" alt="Resume Tips">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">5 Resume Mistakes That Keep You From Getting Screened</h3>
                        <p class="blog-meta-info"><span class="meta-date">Mar 30, 2026</span> • <span class="meta-read">4 min read</span></p>
                        <p class="blog-excerpt">Avoid generic statements and learn how to quantify your impact in project experience sections...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Sara Davis</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Career Advice</span>
                        </div>
                    </div>
                </a>

                <!-- Career Card 6 -->
                <a href="#" class="blog-card hidden" data-category="career">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?auto=format&fit=crop&w=400&q=80" alt="Networking">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">How to Leverage LinkedIn for Organic Tech Referrals</h3>
                        <p class="blog-meta-info"><span class="meta-date">Mar 15, 2026</span> • <span class="meta-read">5 min read</span></p>
                        <p class="blog-excerpt">Step-by-step guidance on structuring outreach messages and engaging with engineers...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Rahul Kapoor</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Career Advice</span>
                        </div>
                    </div>
                </a>

                <!-- ================= STUDY GUIDES ================= -->
                
                <!-- Study Card 1 -->
                <a href="#" class="blog-card hidden" data-category="study">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=400&q=80" alt="Web Development Roadmap">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">The Roadmap to Mastering MERN Stack Development</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 15, 2026</span> • <span class="meta-read">6 min read</span></p>
                        <p class="blog-excerpt">A complete step-by-step pathway starting from basic JavaScript to building advanced apps...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Amit Verma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Study Guides</span>
                        </div>
                    </div>
                </a>

                <!-- Study Card 2 -->
                <a href="#" class="blog-card hidden" data-category="study">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?auto=format&fit=crop&w=400&q=80" alt="Time Management Students">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Time Management Secrets for Online Learning Success</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 08, 2026</span> • <span class="meta-read">5 min read</span></p>
                        <p class="blog-excerpt">Balancing college exams, tutorials, and internships can be hard. Learn how to plan...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Dr. Priya Sharma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Study Guides</span>
                        </div>
                    </div>
                </a>

                <!-- Study Card 3 -->
                <a href="#" class="blog-card hidden" data-category="study">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1506784983877-45594efa4cbe?auto=format&fit=crop&w=400&q=80" alt="Data Structures Guide">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Mastering Data Structures &amp; Algorithms in Python</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 22, 2026</span> • <span class="meta-read">7 min read</span></p>
                        <p class="blog-excerpt">Learn key DSA concepts like trees, graphs, sorting, and big O analysis with code snippets...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Neha Joshi</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Study Guides</span>
                        </div>
                    </div>
                </a>

                <!-- Study Card 4 -->
                <a href="#" class="blog-card hidden" data-category="study">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=400&q=80" alt="SQL Basics">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">SQL Query Practice Guide: From Joins to Subqueries</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 08, 2026</span> • <span class="meta-read">6 min read</span></p>
                        <p class="blog-excerpt">Perfect your databases skills with interactive SQL practice sets designed for interviews...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Rahul Kapoor</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Study Guides</span>
                        </div>
                    </div>
                </a>

                <!-- Study Card 5 -->
                <a href="#" class="blog-card hidden" data-category="study">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1497633762265-9d179a990aa6?auto=format&fit=crop&w=400&q=80" alt="Reading tech docs">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">How to Read Technical Documentation Like a Senior Dev</h3>
                        <p class="blog-meta-info"><span class="meta-date">Mar 22, 2026</span> • <span class="meta-read">5 min read</span></p>
                        <p class="blog-excerpt">Tips on navigating MDN Web Docs, API reference files, and open-source GitHub wikis...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Sara Davis</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Study Guides</span>
                        </div>
                    </div>
                </a>

                <!-- Study Card 6 -->
                <a href="#" class="blog-card hidden" data-category="study">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1501504905252-473c47e087f8?auto=format&fit=crop&w=400&q=80" alt="JS Engine">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Under the Hood: How the JavaScript Engine Works</h3>
                        <p class="blog-meta-info"><span class="meta-date">Mar 05, 2026</span> • <span class="meta-read">8 min read</span></p>
                        <p class="blog-excerpt">Explore the Event Loop, Call Stack, Memory Heap, and Callback Queue in V8 engine...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Amit Verma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Study Guides</span>
                        </div>
                    </div>
                </a>

                <!-- ================= DESIGN INSIGHTS ================= -->
                
                <!-- Design Card 1 -->
                <a href="#" class="blog-card hidden" data-category="design">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=400&q=80" alt="UIUX Design Trends">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">UI/UX Design Principles: Crafting Clean Interfaces</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 19, 2026</span> • <span class="meta-read">5 min read</span></p>
                        <p class="blog-excerpt">Master spacing, grids, visual hierarchies, and responsive typography in modern Figma files...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Dr. Priya Sharma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Design Insights</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 2 -->
                <a href="#" class="blog-card hidden" data-category="design">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?auto=format&fit=crop&w=400&q=80" alt="Typography Guide">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Typography Secrets: Selecting Fonts That Match Your Brand</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 09, 2026</span> • <span class="meta-read">6 min read</span></p>
                        <p class="blog-excerpt">Learn how font weights, line heights, and hierarchy impact user reading retention rates...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Sara Davis</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Design Insights</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 3 -->
                <a href="#" class="blog-card hidden" data-category="design">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1561070791-26c113006238?auto=format&fit=crop&w=400&q=80" alt="Design Systems">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Building Responsive Design Systems from Scratch</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 29, 2026</span> • <span class="meta-read">8 min read</span></p>
                        <p class="blog-excerpt">Step-by-step tutorial on tokenizing spacing, colors, and layout units for web systems...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Neha Joshi</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Design Insights</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 4 -->
                <a href="#" class="blog-card hidden" data-category="design">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1541462608143-67571c6738dd?auto=format&fit=crop&w=400&q=80" alt="Figma Plugins">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Top 10 Figma Plugins to 10x Your Design Workflow</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 12, 2026</span> • <span class="meta-read">4 min read</span></p>
                        <p class="blog-excerpt">Automate asset styling exports, wireframe patterns, and color contrast ratios easily...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Amit Verma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Design Insights</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 5 -->
                <a href="#" class="blog-card hidden" data-category="design">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=400&q=80" alt="Contrast Ratios">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Accessibility in Design: Meeting WCAG Standards</h3>
                        <p class="blog-meta-info"><span class="meta-date">Mar 25, 2026</span> • <span class="meta-read">6 min read</span></p>
                        <p class="blog-excerpt">Learn how to test color contrast ratios, screen readers flow, and button target sizes...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Sara Davis</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Design Insights</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 6 -->
                <a href="#" class="blog-card hidden" data-category="design">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1581291518857-4e27b48ff24e?auto=format&fit=crop&w=400&q=80" alt="Mobile UIUX">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Mobile First Design: Designing for Handheld Viewports</h3>
                        <p class="blog-meta-info"><span class="meta-date">Mar 10, 2026</span> • <span class="meta-read">5 min read</span></p>
                        <p class="blog-excerpt">Structuring UI layouts that fit smaller screen spaces without cluttering content modules...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Rahul Kapoor</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Design Insights</span>
                        </div>
                    </div>
                </a>

                <!-- ================= BUSINESS GROWTH ================= -->
                
                <!-- Biz Card 1 -->
                <a href="#" class="blog-card hidden" data-category="business">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=400&q=80" alt="Business Growth">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">SEO Strategies: Driving Traffic to Your Tech Startup</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 17, 2026</span> • <span class="meta-read">7 min read</span></p>
                        <p class="blog-excerpt">How keyword density, backlink analysis, and core web vitals boost organic search listings...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Amit Verma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Business Growth</span>
                        </div>
                    </div>
                </a>

                <!-- Biz Card 2 -->
                <a href="#" class="blog-card hidden" data-category="business">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1432888622747-4eb9a8efeb07?auto=format&fit=crop&w=400&q=80" alt="Social Media Lead Gen">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Leveraging Social Media for Educational Lead Generation</h3>
                        <p class="blog-meta-info"><span class="meta-date">May 07, 2026</span> • <span class="meta-read">6 min read</span></p>
                        <p class="blog-excerpt">Learn how structured campaigns and value-first social posts scale platform users...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Dr. Priya Sharma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Business Growth</span>
                        </div>
                    </div>
                </a>

                <!-- Biz Card 3 -->
                <a href="#" class="blog-card hidden" data-category="business">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=400&q=80" alt="B2B Sales">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">B2B SaaS Sales Framework: From Lead to Close</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 24, 2026</span> • <span class="meta-read">8 min read</span></p>
                        <p class="blog-excerpt">An industry breakdown of modern sales funnels, contract structures, and retention metrics...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Neha Joshi</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Business Growth</span>
                        </div>
                    </div>
                </a>

                <!-- Biz Card 4 -->
                <a href="#" class="blog-card hidden" data-category="business">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1557200134-90327ee9fafa?auto=format&fit=crop&w=400&q=80" alt="Email Marketing Growth">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Building an Email Newsletter That Actually Converts</h3>
                        <p class="blog-meta-info"><span class="meta-date">Apr 05, 2026</span> • <span class="meta-read">5 min read</span></p>
                        <p class="blog-excerpt">Avoid spam folders, master subject lines, and optimize call-to-action click rates...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Sara Davis</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Business Growth</span>
                        </div>
                    </div>
                </a>

                <!-- Biz Card 5 -->
                <a href="#" class="blog-card hidden" data-category="business">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=400&q=80" alt="Affiliate Marketing Growth">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Affiliate Networks: Accelerating Your Business Reach</h3>
                        <p class="blog-meta-info"><span class="meta-date">Mar 20, 2026</span> • <span class="meta-read">6 min read</span></p>
                        <p class="blog-excerpt">Learn how to attract high-performing affiliates and structure fair commission tier levels...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Amit Verma</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Business Growth</span>
                        </div>
                    </div>
                </a>

                <!-- Biz Card 6 -->
                <a href="#" class="blog-card hidden" data-category="business">
                    <div class="blog-image-box">
                        <img src="https://images.unsplash.com/photo-1553481187-be93c21490a9?auto=format&fit=crop&w=400&q=80" alt="Performance Ads">
                    </div>
                    <div class="blog-info">
                        <h3 class="blog-title">Performance Marketing: Setting Up Profitable Ads</h3>
                        <p class="blog-meta-info"><span class="meta-date">Mar 01, 2026</span> • <span class="meta-read">8 min read</span></p>
                        <p class="blog-excerpt">A beginner's guide to auditing CPC metrics, budget scaling caps, and target audiences...</p>
                        <div class="blog-author-box">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80" alt="Author avatar">
                            <span class="author-name">Rahul Kapoor</span>
                        </div>
                        <div class="blog-badge-container">
                            <span class="blog-badge">Business Growth</span>
                        </div>
                    </div>
                </a>

            </div> <!-- /blogs-grid -->
        </div> <!-- /blogs-grid-container -->

        <!-- Bottom Redirection Link -->
        <a href="#" class="blogs-show-all" id="blogShowAllLink">
            Show all Tech Trends articles <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div> <!-- /blogs-container -->
</section>

<!-- Slider & Filter Script -->
<script>
    function filterBlogs(category) {
        // 1. Manage Active Tab Highlight State
        const tabs = document.querySelectorAll('.blog-filter-tab');
        tabs.forEach(tab => {
            if(tab.id === 'tab-blog-' + (category === 'tech' ? 'tech' : category === 'career' ? 'career' : category === 'study' ? 'study' : category === 'design' ? 'design' : 'biz')) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        // 2. Filter Blog Cards with display toggle
        const cards = document.querySelectorAll('.blog-card');
        cards.forEach(card => {
            const cardCategory = card.getAttribute('data-category');
            if (cardCategory === category) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });

        // 3. Update dynamic "Show All" text
        const showAllLink = document.getElementById('blogShowAllLink');
        let categoryLabel = "Tech Trends";
        if (category === 'career') categoryLabel = "Career Advice";
        else if (category === 'study') categoryLabel = "Study Guides";
        else if (category === 'design') categoryLabel = "Design Insights";
        else if (category === 'business') categoryLabel = "Business Growth";
        showAllLink.innerHTML = `Show all ${categoryLabel} articles <i class="fa-solid fa-arrow-right"></i>`;

        // 4. Reset Slider Scroll Position and update Arrow states
        const grid = document.getElementById('blogsGrid');
        grid.scrollLeft = 0;
        setTimeout(updateBlogNavButtons, 50);
    }

    // Slider Smooth Scrolling Action
    function slideBlogGrid(direction) {
        const grid = document.getElementById('blogsGrid');
        const scrollAmount = grid.clientWidth * 0.75;
        if (direction === 'prev') {
            grid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            grid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
        setTimeout(updateBlogNavButtons, 400);
    }

    // Dynamic slider next/prev button boundaries
    function updateBlogNavButtons() {
        const grid = document.getElementById('blogsGrid');
        const prevBtn = document.getElementById('blogPrevBtn');
        const nextBtn = document.getElementById('blogNextBtn');
        
        if (!grid || !prevBtn || !nextBtn) return;

        if (window.innerWidth <= 768) {
            return;
        }

        if (grid.scrollLeft <= 10) {
            prevBtn.disabled = true;
        } else {
            prevBtn.disabled = false;
        }

        const isEnd = grid.scrollLeft + grid.clientWidth >= grid.scrollWidth - 10;
        if (isEnd) {
            nextBtn.disabled = true;
        } else {
            nextBtn.disabled = false;
        }
    }

    // Set Defaults on document load
    document.addEventListener('DOMContentLoaded', () => {
        filterBlogs('tech');
        window.addEventListener('resize', updateBlogNavButtons);
    });
</script>
