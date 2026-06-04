<!-- Link to YouTube Classes Stylesheet -->
<link rel="stylesheet" href="assets/css/youtube_classes.css">

<section class="yt-classes-section">
    <div class="yt-classes-container">
        
        <!-- Professional Header with inline YouTube branding -->
        <div class="yt-classes-header">
            <div class="yt-header-left">
                <h2><i class="fa-brands fa-youtube yt-heading-icon"></i> Free YouTube Classes</h2>
                <p>High-quality, comprehensive video lectures and coding roadmaps — completely free.</p>
            </div>
        </div>

        <!-- Slim Channel Subscribe Banner -->
        <div class="yt-channel-banner">
            <div class="yt-channel-banner-left">
                <div class="yt-channel-avatar-circle">MG</div>
                <div class="yt-channel-details">
                    <span class="yt-channel-name">MG Education Tech <i class="fa-solid fa-circle-check yt-verified"></i></span>
                    <span class="yt-sub-count">185K+ Subscribers • 120+ Free Videos</span>
                </div>
            </div>
            <a href="https://youtube.com" target="_blank" class="yt-subscribe-btn">
                <i class="fa-brands fa-youtube"></i>
                <span>Subscribe</span>
                <i class="fa-solid fa-bell bell-icon"></i>
            </a>
        </div>

        <!-- Filter Tab Buttons -->
        <div class="yt-filter-wrapper">
            <div class="yt-filter-tabs">
                <button class="yt-filter-tab active" id="tab-yt-web" onclick="filterYTVideos('web-dev')">Web Development</button>
                <button class="yt-filter-tab" id="tab-yt-dsa" onclick="filterYTVideos('python-dsa')">Python &amp; DSA</button>
                <button class="yt-filter-tab" id="tab-yt-android" onclick="filterYTVideos('android')">Android App Dev</button>
                <button class="yt-filter-tab" id="tab-yt-uiux" onclick="filterYTVideos('uiux')">UI/UX Design</button>
            </div>
        </div>

        <!-- Slider Grid Wrapper -->
        <div class="yt-grid-container">
            
            <!-- Slider Arrows (Udemy / PW style) -->
            <button class="yt-nav-btn prev-btn" id="ytPrevBtn" onclick="slideYTGrid('prev')" aria-label="Previous Videos">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            
            <button class="yt-nav-btn next-btn" id="ytNextBtn" onclick="slideYTGrid('next')" aria-label="Next Videos">
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            <!-- YouTube Video Cards Grid -->
            <div class="yt-grid" id="ytGrid" onscroll="updateYTNavButtons()">
                
                <!-- ================= WEB DEVELOPMENT ================= -->
                
                <!-- Card 1 -->
                <a href="https://youtube.com" target="_blank" class="yt-card" data-category="web-dev">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1547082299-de196ea013d6?auto=format&fit=crop&w=400&q=80" alt="MERN Course">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">12:45:10</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">MERN Stack Full Course for Beginners in Hindi (2026 Edition)</h3>
                        <p class="yt-excerpt">Build a complete E-Commerce application from scratch using React, Node.js, Express, and MongoDB...</p>
                        <p class="yt-meta"><span class="yt-views">320K views</span> • <span class="yt-date">2 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Card 2 -->
                <a href="https://youtube.com" target="_blank" class="yt-card" data-category="web-dev">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1633356122544-f134324a6cee?auto=format&fit=crop&w=400&q=80" alt="React Course">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">5:30:15</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">React JS Complete Tutorial: Zero to Hero in One Video</h3>
                        <p class="yt-excerpt">Learn hooks, state management, routing, and context API with 3 live frontend projects...</p>
                        <p class="yt-meta"><span class="yt-views">540K views</span> • <span class="yt-date">4 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Card 3 -->
                <a href="https://youtube.com" target="_blank" class="yt-card" data-category="web-dev">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=400&q=80" alt="JavaScript Course">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">8:20:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">JavaScript Masterclass: Advanced ES6, DOM &amp; Async JavaScript</h3>
                        <p class="yt-excerpt">Understand Event Loop, closures, prototypes, promises, and API calling like an expert...</p>
                        <p class="yt-meta"><span class="yt-views">210K views</span> • <span class="yt-date">5 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Card 4 -->
                <a href="https://youtube.com" target="_blank" class="yt-card" data-category="web-dev">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1621839673705-6617adf9e890?auto=format&fit=crop&w=400&q=80" alt="HTML CSS Course">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">4:12:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">HTML5 &amp; CSS3 Crash Course: Build Responsive Websites</h3>
                        <p class="yt-excerpt">Learn modern Flexbox, Grid layout systems, media queries, and styling patterns...</p>
                        <p class="yt-meta"><span class="yt-views">180K views</span> • <span class="yt-date">1 month ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Card 5 -->
                <a href="https://youtube.com" target="_blank" class="yt-card" data-category="web-dev">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1593720213428-28a5b9e94613?auto=format&fit=crop&w=400&q=80" alt="Tailwind CSS">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">2:45:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Tailwind CSS Complete Guide: Style Webpages Faster</h3>
                        <p class="yt-excerpt">Learn layout alignment, responsive breakpoints, custom styling utility patterns...</p>
                        <p class="yt-meta"><span class="yt-views">95K views</span> • <span class="yt-date">3 weeks ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Card 6 -->
                <a href="https://youtube.com" target="_blank" class="yt-card" data-category="web-dev">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?auto=format&fit=crop&w=400&q=80" alt="Next JS">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">7:15:30</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Next.js 14 App Router Full Course (Server Components &amp; Actions)</h3>
                        <p class="yt-excerpt">Master SSR, static regeneration, dynamic routing, and database optimization...</p>
                        <p class="yt-meta"><span class="yt-views">150K views</span> • <span class="yt-date">3 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- ================= PYTHON & DSA ================= -->
                
                <!-- Python Card 1 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="python-dsa">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=400&q=80" alt="Python Tutorial">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">10:45:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Python Full Course for Beginners: Zero to Professional</h3>
                        <p class="yt-excerpt">Learn syntax, loops, OOP concepts, file handling, and package scripting in Python...</p>
                        <p class="yt-meta"><span class="yt-views">420K views</span> • <span class="yt-date">6 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Python Card 2 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="python-dsa">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=400&q=80" alt="DSA Course">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">15:30:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Data Structures &amp; Algorithms Course in Python</h3>
                        <p class="yt-excerpt">Complete coding bootcamp covering Arrays, Linked Lists, Trees, Stacks, Queues, Graphs...</p>
                        <p class="yt-meta"><span class="yt-views">280K views</span> • <span class="yt-date">5 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Python Card 3 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="python-dsa">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?auto=format&fit=crop&w=400&q=80" alt="SQL Database">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">4:50:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">SQL Crash Course for Interviews (Joins, Queries, Views)</h3>
                        <p class="yt-excerpt">Learn schema creation, data manipulation, relational tables, query tuning guidelines...</p>
                        <p class="yt-meta"><span class="yt-views">190K views</span> • <span class="yt-date">3 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Python Card 4 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="python-dsa">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=400&q=80" alt="Pandas Course">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">3:15:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Pandas &amp; NumPy Data Analysis Tutorial for Beginners</h3>
                        <p class="yt-excerpt">Clean, transform, merge, and visualize datasets with python pandas tools...</p>
                        <p class="yt-meta"><span class="yt-views">110K views</span> • <span class="yt-date">2 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Python Card 5 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="python-dsa">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1618401471353-b98aedd07871?auto=format&fit=crop&w=400&q=80" alt="GitHub Guide">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">2:10:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Git &amp; GitHub Mastery Guide: Branching, Merging &amp; PRs</h3>
                        <p class="yt-excerpt">Collaborate with other developers, resolve merge conflicts, handle git configurations...</p>
                        <p class="yt-meta"><span class="yt-views">140K views</span> • <span class="yt-date">1 month ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Python Card 6 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="python-dsa">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1607799279861-4dd421887fb3?auto=format&fit=crop&w=400&q=80" alt="Competitive Programming">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">5:45:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Competitive Programming Bootcamp: Dynamic Programming Problems</h3>
                        <p class="yt-excerpt">Solve advanced array and matrix optimization coding exercises live with explanation...</p>
                        <p class="yt-meta"><span class="yt-views">85K views</span> • <span class="yt-date">2 weeks ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- ================= ANDROID APP DEV ================= -->
                
                <!-- Android Card 1 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="android">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?auto=format&fit=crop&w=400&q=80" alt="Flutter Tutorial">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">9:30:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Flutter Android &amp; iOS App Development Complete Course</h3>
                        <p class="yt-excerpt">Build cross-platform applications using Dart language, custom layouts, state management...</p>
                        <p class="yt-meta"><span class="yt-views">280K views</span> • <span class="yt-date">4 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Android Card 2 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="android">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1551650975-87deedd944c3?auto=format&fit=crop&w=400&q=80" alt="Kotlin Course">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">8:45:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Android Development with Kotlin: Jetpack Compose Bootcamp</h3>
                        <p class="yt-excerpt">Build UI layouts, handle local Room databases, call HTTP requests inside Kotlin threads...</p>
                        <p class="yt-meta"><span class="yt-views">175K views</span> • <span class="yt-date">3 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Android Card 3 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="android">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?auto=format&fit=crop&w=400&q=80" alt="React Native">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">6:12:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">React Native Mobile App Development Course for Beginners</h3>
                        <p class="yt-excerpt">Build mobile apps using JavaScript/React framework libraries, compile native packages...</p>
                        <p class="yt-meta"><span class="yt-views">110K views</span> • <span class="yt-date">5 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Android Card 4 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="android">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1522542550221-31fd19575a2d?auto=format&fit=crop&w=400&q=80" alt="App UI Design">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">3:30:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Figma to Flutter UI: Transforming Mockups into Real Apps</h3>
                        <p class="yt-excerpt">Design visual assets inside Figma and code identical layout structures in Dart widgets...</p>
                        <p class="yt-meta"><span class="yt-views">64K views</span> • <span class="yt-date">2 weeks ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Android Card 5 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="android">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=400&q=80" alt="Firebase integration">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">4:10:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Firebase and Flutter Integration: Authentication &amp; Firestore</h3>
                        <p class="yt-excerpt">Enable email sign-in, google logins, real-time sync database tables inside flutter project...</p>
                        <p class="yt-meta"><span class="yt-views">82K views</span> • <span class="yt-date">1 month ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Android Card 6 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="android">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1605810230434-7631ac76ec81?auto=format&fit=crop&w=400&q=80" alt="Play Store Publishing">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">1:45:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">How to Publish Android App to Google Play Store (2026 Guide)</h3>
                        <p class="yt-excerpt">Sign your app bundles, set up privacy policies, prepare app store listings step by step...</p>
                        <p class="yt-meta"><span class="yt-views">130K views</span> • <span class="yt-date">2 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- ================= UI/UX DESIGN ================= -->
                
                <!-- Design Card 1 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="uiux">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=400&q=80" alt="Figma Tutorial">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">6:45:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Figma UI/UX Design Course: Complete Guide from Scratch</h3>
                        <p class="yt-excerpt">Learn grids, components, auto-layouts, variables, animations, and high-fidelity prototype styles...</p>
                        <p class="yt-meta"><span class="yt-views">240K views</span> • <span class="yt-date">3 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 2 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="uiux">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?auto=format&fit=crop&w=400&q=80" alt="UI Design Principles">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">3:10:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">UI Design Principles: Visual Hierarchy, Spacing &amp; Fonts</h3>
                        <p class="yt-excerpt">Learn visual contrast patterns, vertical grids, selection alignment rules, margins...</p>
                        <p class="yt-meta"><span class="yt-views">115K views</span> • <span class="yt-date">2 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 3 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="uiux">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1561070791-26c113006238?auto=format&fit=crop&w=400&q=80" alt="Photoshop Branding">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">5:20:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Photoshop &amp; Illustrator Crash Course: Brand Assets</h3>
                        <p class="yt-excerpt">Design vectors, branding collaterals, digital banners, photo retouch elements...</p>
                        <p class="yt-meta"><span class="yt-views">155K views</span> • <span class="yt-date">4 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 4 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="uiux">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1541462608143-67571c6738dd?auto=format&fit=crop&w=400&q=80" alt="Portfolio Building">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">2:15:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">How to Build a UI/UX Portfolio That Lands Premium Jobs</h3>
                        <p class="yt-excerpt">Case studies layout patterns, user flow mapping, wireframe summaries presentation...</p>
                        <p class="yt-meta"><span class="yt-views">95K views</span> • <span class="yt-date">3 weeks ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 5 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="uiux">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1581291518857-4e27b48ff24e?auto=format&fit=crop&w=400&q=80" alt="Wireframing">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">1:30:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Wireframing Guide: Low-Fi to High-Fi Prototyping</h3>
                        <p class="yt-excerpt">Understand user research translation into low-fidelity UI layout grids...</p>
                        <p class="yt-meta"><span class="yt-views">45K views</span> • <span class="yt-date">1 month ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

                <!-- Design Card 6 -->
                <a href="https://youtube.com" target="_blank" class="yt-card hidden" data-category="uiux">
                    <div class="yt-thumbnail-box">
                        <img src="https://images.unsplash.com/photo-1561070791-36c11767b26a?auto=format&fit=crop&w=400&q=80" alt="Figma Variables">
                        <div class="play-overlay">
                            <div class="play-button-glowing">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                        <span class="video-duration">2:50:00</span>
                    </div>
                    <div class="yt-info">
                        <h3 class="yt-title">Figma Variables &amp; Tokens: Dark Mode Systems Tutorial</h3>
                        <p class="yt-excerpt">Set up variables for custom colors, screen dimensions, spacing units in systems...</p>
                        <p class="yt-meta"><span class="yt-views">72K views</span> • <span class="yt-date">2 months ago</span></p>
                        <div class="yt-author-row">
                            <div class="yt-avatar">MG</div>
                            <span class="yt-author-name">MG Education Tech</span>
                        </div>
                    </div>
                </a>

            </div> <!-- /yt-grid -->
        </div> <!-- /yt-grid-container -->

        <!-- Bottom Redirection Link -->
        <a href="https://youtube.com" target="_blank" class="yt-show-all" id="ytShowAllLink">
            Visit YouTube channel for more Web Development lectures <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div> <!-- /yt-container -->
</section>

<!-- Slider & Filter Script -->
<script>
    function filterYTVideos(category) {
        // 1. Manage Active Tab Highlight State
        const tabs = document.querySelectorAll('.yt-filter-tab');
        tabs.forEach(tab => {
            if(tab.id === 'tab-yt-' + (category === 'web-dev' ? 'web' : category === 'python-dsa' ? 'dsa' : category === 'android' ? 'android' : 'uiux')) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        // 2. Filter Video Cards with display toggle
        const cards = document.querySelectorAll('.yt-card');
        cards.forEach(card => {
            const cardCategory = card.getAttribute('data-category');
            if (cardCategory === category) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });

        // 3. Update dynamic "Show All" text
        const showAllLink = document.getElementById('ytShowAllLink');
        let categoryLabel = "Web Development";
        if (category === 'python-dsa') categoryLabel = "Python & DSA";
        else if (category === 'android') categoryLabel = "Android App Dev";
        else if (category === 'uiux') categoryLabel = "UI/UX Design";
        showAllLink.innerHTML = `Visit YouTube channel for more ${categoryLabel} lectures <i class="fa-solid fa-arrow-right"></i>`;

        // 4. Reset Slider Scroll Position and update Arrow states
        const grid = document.getElementById('ytGrid');
        grid.scrollLeft = 0;
        setTimeout(updateYTNavButtons, 50);
    }

    // Slider Smooth Scrolling Action
    function slideYTGrid(direction) {
        const grid = document.getElementById('ytGrid');
        const scrollAmount = grid.clientWidth * 0.75;
        if (direction === 'prev') {
            grid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            grid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
        setTimeout(updateYTNavButtons, 400);
    }

    // Dynamic slider next/prev button boundaries
    function updateYTNavButtons() {
        const grid = document.getElementById('ytGrid');
        const prevBtn = document.getElementById('ytPrevBtn');
        const nextBtn = document.getElementById('ytNextBtn');
        
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
        filterYTVideos('web-dev');
        window.addEventListener('resize', updateYTNavButtons);
    });
</script>
