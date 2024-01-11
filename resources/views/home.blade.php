<!DOCTYPE html>
<html lang="en"> 
<head>
    <title>XGPT - Use Nightbot as AI chatbot powered by ChatGPT</title>

    <!-- Meta -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <meta name="description" content="XGPT - Use Nightbot as AI chatbot powered by ChatGPT">
    <meta name="author" content="xgerhard">
    
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- Plugins CSS -->
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.15.2/styles/atom-one-dark.min.css">
    <link rel="stylesheet" href="assets/plugins/simplelightbox/simple-lightbox.min.css">

    <!-- Theme CSS -->  
    <link id="theme-style" rel="stylesheet" href="assets/css/theme.css">

</head> 

<body class="docs-page">    
    <header class="header fixed-top">	    
        <div class="branding docs-branding">
            <div class="container-fluid position-relative py-2">
                <div class="docs-logo-wrapper">
					<button id="docs-sidebar-toggler" class="docs-sidebar-toggler docs-sidebar-visible me-2 d-xl-none" type="button">
	                    <span></span>
	                    <span></span>
	                    <span></span>
	                </button>
	                <div class="site-logo"><a class="navbar-brand" href="/"><span class="logo-text">X<span class="text-alt">GPT</span></span></a></div>    
                </div><!--//docs-logo-wrapper-->
	            <div class="docs-top-utilities d-flex justify-content-end align-items-center">	
					<ul class="social-list list-inline mx-md-3 mx-lg-5 mb-0 d-none d-lg-flex">
						<li class="list-inline-item"><a href="https://github.com/xgerhard/XGPT" target="blank"><i class="fa fa-github fa-fw"></i></a></li>
			            <li class="list-inline-item"><a href="mailto:hi@gerhard.dev" target="blank"><i class="fa fa-envelope fa-fw"></i></a></li>
		            </ul><!--//social-list-->
		            <a href="https://xgpt.gerhard.dev/dashboard" class="btn btn-secondary d-none d-lg-flex">Sign in with Nightbot</a>
	            </div><!--//docs-top-utilities-->
            </div><!--//container-->
        </div><!--//branding-->
    </header><!--//header-->
    
    
    <div class="docs-wrapper">
	    <div id="docs-sidebar" class="docs-sidebar">
		    <nav id="docs-nav" class="docs-nav navbar">
			    <ul class="section-items list-unstyled nav flex-column pb-3">
				    <li class="nav-item section-title"><a class="nav-link scrollto active" href="#section-1"><span class="theme-icon-holder me-2"><i class="fa fa-map-signs"></i></span>Introduction</a></li>
				    <li class="nav-item"><a class="nav-link scrollto" href="#item-1-1">Examples</a></li>
				    <li class="nav-item"><a class="nav-link scrollto" href="#item-1-2">Conversations</a></li>
				    <li class="nav-item"><a class="nav-link scrollto" href="#item-1-3">Nightbot</a></li>
				    <li class="nav-item section-title mt-3"><a class="nav-link scrollto" href="#section-2"><span class="theme-icon-holder me-2"><i class="fa fa-usd"></i></span>Pricing</a></li>
				    <li class="nav-item"><a class="nav-link scrollto" href="#item-2-1">Sponsors</a></li>
				    <li class="nav-item section-title mt-3"><a class="nav-link scrollto" href="#section-3"><span class="theme-icon-holder me-2"><i class="fa fa-arrow-down"></i></span>Installation</a></li>
				    <li class="nav-item"><a class="nav-link scrollto" href="#item-3-1">Automatic</a></li>
				    <li class="nav-item"><a class="nav-link scrollto" href="#item-3-2">Manual</a></li>
				    <li class="nav-item section-title mt-3"><a class="nav-link scrollto" href="#section-4"><span class="theme-icon-holder me-2"><i class="fa fa-lightbulb-o"></i></span>Help</a></li>
				    <li class="nav-item"><a class="nav-link scrollto" href="#item-4-1">Contact</a></li>
				    <li class="nav-item"><a class="nav-link scrollto" href="#item-4-2">Contribute</a></li>
			    </ul>
		    </nav><!--//docs-nav-->
	    </div><!--//docs-sidebar-->
	    <div class="docs-content">
		    <div class="container">
			    <article class="docs-article" id="section-1">
				    <header class="docs-header">
					    <h1 class="docs-heading">XGPT <span class="docs-time">Last updated: 11 Jan 2024</span></h1>
                        <h2>Use Nightbot as AI chatbot powered by ChatGPT</h2>
					    <section class="docs-intro">
                            <p>Spice up your chat by adding an AI chatbot to your stream and make Nightbot your stream assistant. You can use this bot to get answers to your questions, help you create a stream title or raid message, or maybe just for a fun chat. This app is powered by <a href="https://openai.com/blog/chatgpt" target="blank">OpenAI ChatGPT</a>.</p>
						</section><!--//docs-intro-->
				    </header>
					<div class="callout-block callout-block-info">
						<div class="content">
							<h4 class="callout-title">
								<span class="callout-icon-holder me-1">
									<i class="fa fa-info-circle"></i>
								</span><!--//icon-holder-->
								<a href="#section-3">Don't feel like reading? Click me to skip to installation.</a>
							</h4>
						</div><!--//content-->
					</div><!--//callout-block-->
				    <section class="docs-section" id="item-1-1">
						<h2 class="section-heading">Examples</h2>
						<p>A couple examples, need help, info, or a fun chat?</p>
                        <div class="simplelightbox-gallery-intro row mb-3">
                            <div class="col-12 col-md-4 mb-3">
                                <a href="assets/images/intro1c8033249c703c00a20313ff121f55a86.png"><img class="figure-img img-fluid shadow rounded" src="assets/images/intro1c8033249c703c00a20313ff121f55a86.png" alt="Have a fun chat"/></a>
                            </div>
                            <div class="col-12 col-md-4 mb-3">
                                <a href="assets/images/intro2f7bcebd37828dd84c06a6e9784fa36c2.png"><img class="figure-img img-fluid shadow rounded" src="assets/images/intro2f7bcebd37828dd84c06a6e9784fa36c2.png" alt="Need a stream title?"/></a>
                            </div><!--//col-->
                            <div class="col-12 col-md-4 mb-3">
                                <a href="assets/images/intro307ed17a629fa6f23d37983b576e4cd4a.png"><img class="figure-img img-fluid shadow rounded" src="assets/images/intro307ed17a629fa6f23d37983b576e4cd4a.png" alt="Need info on a topic?"/></a>
                            </div><!--//col-->
                        </div><!--//gallery-->
					</section><!--//section-->
					
					<section class="docs-section" id="item-1-2">
						<h2 class="section-heading">Conversations</h2>
                        <p>By default each message sent to the bot is seen as a new conversiation, however if you want to continue the previous conversation use the code at the end of the bot's message at the <u>start</u> of your new message.</p>
                        <div class="simplelightbox-gallery-convo row mb-3">
                            <div class="col-12 col-md-4 mb-3">
                                <a href="assets/images/convo104d6994f86f4e1cab4370d045f74f052.png"><img class="figure-img img-fluid shadow rounded" src="assets/images/convo104d6994f86f4e1cab4370d045f74f052.png" alt="Rickrolling"/></a>
                            </div>
                            <div class="col-12 col-md-4 mb-3">
                                <a href="assets/images/convo28c1a69b8d788241a1651d8393336ef6c.png"><img class="figure-img img-fluid shadow rounded" src="assets/images/convo28c1a69b8d788241a1651d8393336ef6c.png" alt="Easy math solutions"/></a>
                            </div><!--//col-->
                            <div class="col-12 col-md-4 mb-3">
                                <a href="assets/images/convo32939504b5d5e273853de60accaa6e3c2.png"><img class="figure-img img-fluid shadow rounded" src="assets/images/convo32939504b5d5e273853de60accaa6e3c2.png" alt="AI != human"/></a>
                            </div><!--//col-->
                        </div><!--//gallery-->
					</section><!--//section-->
					
					<section class="docs-section" id="item-1-3">
						<h2 class="section-heading">Nightbot</h2>
                        <p>Nightbot is a chat bot for Twitch, YouTube, and Trovo that allows you to automate your live stream's chat with moderation and new features, allowing you to spend more time entertaining your viewers.</p>
                        <p>Setting up Nightbot is easy, simply sign in at: <a href="https://nightbot.tv/" target="blank">nightbot.tv</a> and click the "Join Channel" button, that's it!</p>
						<h5>When will other bots be supported?</h5>
                        <p>Most likely never. By using Nightbot we can integrate our app easily and provide a one click <a href="#section-3">installation</a> for users.</p>                        
					</section><!--//section-->
			    </article>
			    
			    <article class="docs-article" id="section-2">
				    <header class="docs-header">
					    <h1 class="docs-heading">Pricing</h1>
					    <section class="docs-intro">
						    <p><b>Free!</b> This app is, and will always be free. However please read this article carefully.</p>
                            <p>This app is using the OpenAI ChatGPT API, which is <b>not free</b>. The costs are flexible, pay for what you use. Which means more users, higher costs.</p>
                            <p>More information here: <a href="https://openai.com/pricing" target="blank">openai.com/pricing</a>. We use the gpt-3.5-turbo model which currectly costs $0.002 / 1K tokens. Tokens are calculated based on the amount of text that has to be processed. A helpful rule of thumb is that one token generally corresponds to ~4 characters of text for common English text. This translates to roughly ¾ of a word (so 100 tokens ~= 75 words).<p>
                            <h5>Who is paying?</h5>
                            <p>The community. Users who want to support this project can help cover the costs here: <a href="https://github.com/sponsors/xgerhard" target="blank">github.com/sponsors/xgerhard</a>. Once we run out of tokens for the month, the app will stop working for everyone. Tokens reset on the first day of the month.</p>
                            <p>For the generous users who help keeping this app running, we added a few extra features, see <a href="#item-2-1">Sponsors</a>.</p>
						</section><!--//docs-intro-->
				    </header>
				    <section class="docs-section" id="item-2-1">
						<h2 class="section-heading">Sponsors</h2>
						<p>Do you, or want to, sponsor this app? Great! Head over to: <a href="https://github.com/sponsors/xgerhard" target="blank">github.com/sponsors/xgerhard</a>.</p>
                        <p>Sponsors get a heart ❤️ in front of their name when using the app as recognition of their generosity.</p>
                        <a href="assets/images/sponsord0376151b3df41e34cdc7fcdfcbfcb5b.png"><img class="figure-img img-fluid shadow rounded" src="assets/images/sponsord0376151b3df41e34cdc7fcdfcbfcb5b.png" alt="Sponsors heart"/></a>
                    </section><!--//section-->
			    </article><!--//docs-article-->
			    
			    
			    <article class="docs-article" id="section-3">
				    <header class="docs-header">
					    <h1 class="docs-heading">Installation</h1>
					    <section class="docs-intro">
						    <p>Installation should be short and straight forwarded. Either a one click installation after signing in with Nightbot or copy paste the command in your chat.</p>
						</section><!--//docs-intro-->
				    </header>
				     <section class="docs-section" id="item-3-1">
						<h2 class="section-heading">Automatic</h2>
						<p>Sign in with Nightbot and we will handle the installation for you!</p>
						<p>When you sign in to our dashboard you will be able to customize settings for your personal AI chatbot. Give the bot more and detailed instructions for just your stream. Provide attional information about you or your stream, so the bot can answer more specific questions. Want the bot to speak in a certain language, a certain way, include emojis, be more funny? Endless opportunities and alot of fun!</p>
						<p>You'll also be able to use your own OpenAI API key to avoid monthly limits, which we highly recommend!</p>
                        <p><a href="https://xgpt.gerhard.dev/dashboard" class="btn btn-secondary">Sign in with Nightbot</a></p>
					</section><!--//section-->
					
					<section class="docs-section" id="item-3-2">
						<h2 class="section-heading">Manual</h2>
                        <div class="callout-block callout-block-info">
                            <div class="content">
                                <h4 class="callout-title">
	                                <span class="callout-icon-holder me-1">
		                                <i class="fa fa-info-circle"></i>
		                            </span><!--//icon-holder-->
	                                Copy paste the following command in your chat:
	                            </h4>
                                <p><code>!commands add !ai -cd=5 $(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring))</code></p>
                            </div><!--//content-->
                        </div><!--//callout-block-->
                        <p>Or:</p>
                        <div class="callout-block callout-block-info">
                            <div class="content">
                                <h4 class="callout-title">
	                                <span class="callout-icon-holder me-1">
		                                <i class="fa fa-info-circle"></i>
		                            </span><!--//icon-holder-->
	                                Enter this in the command 'Message' field from your <a href="https://nightbot.tv/commands/custom" target="blank">Nightbot commands dashboard</a>:
	                            </h4>
                                <p><code>$(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring))</code></p>
                            </div><!--//content-->
                        </div><!--//callout-block-->
                        
					</section><!--//section-->
			    </article><!--//docs-article-->
			    
			    <article class="docs-article" id="section-4">
				    <header class="docs-header">
					    <h1 class="docs-heading">Help</h1>
					    <section class="docs-intro">
						    <p></p>
						</section><!--//docs-intro-->
				    </header>
				     <section class="docs-section" id="item-4-1">
						<h2 class="section-heading">Contact</h2>
						<p>Need help with the installation, any questions, or a thank you, feel free to send me a mail at: <a href="mailto:hi@gerhard.dev" target="blank">hi@gerhard.dev</a>.</p>
					</section><!--//section-->
					<section class="docs-section" id="item-4-2">
						<h2 class="section-heading">Contribute</h2>
						<p>The source code is viewable for everyone, have suggestions, improvements, or just curious? View this app on GitHub: <a href="https://github.com/xgerhard/XGPT" target="blank">GitHub.com/xgerhard/XGPT</a>.</p>
					</section><!--//section-->
			    </article><!--//docs-article-->
			    <footer class="footer">
				    <div class="container text-center py-5">
				        <!--/* This template is free as long as you keep the footer attribution link. If you'd like to use the template without the attribution link, you can buy the commercial license via our website: themes.3rdwavemedia.com Thank you for your support. :) */-->
			            <small class="copyright">Created with <span class="sr-only">love</span><i class="fa fa-heart" style="color: #fb866a;"></i> by <a class="theme-link" href="https://gerhard.dev" target="blank">Gerhard</a> and designed by <a class="theme-link" href="http://themes.3rdwavemedia.com" target="_blank">Xiaoying Riley</a>.</small>
				        <ul class="social-list list-unstyled pt-4 mb-0">
						    <li class="list-inline-item"><a href="https://github.com/xgerhard/XGPT" target="blank"><i class="fab fa-github fa-fw"></i></a></li> 
				            <li class="list-inline-item"><a href="mailto:hi@gerhard.dev" target="blank"><i class="fab fa-envelope fa-fw"></i></a></li>
				        </ul><!--//social-list-->
				    </div>
			    </footer>
		    </div> 
	    </div>
    </div><!--//docs-wrapper-->

    <!-- Javascript -->          
    <script src="assets/plugins/popper.min.js"></script>
    <script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>  

    <!-- Page Specific JS -->
    <script src="assets/plugins/smoothscroll.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.15.8/highlight.min.js"></script>
    <script src="assets/js/highlight-custom.js"></script> 
    <script src="assets/plugins/simplelightbox/simple-lightbox.min.js"></script>      
    <script src="assets/plugins/gumshoe/gumshoe.polyfills.min.js"></script> 
    <script src="assets/js/docs.js"></script> 

</body>
</html> 

