***Edit:** Epicness is considering joining the Skymu team in an advisory capacity, and I have implemented all of the improvements, and fixed all the issues, that he outlined in his document. All's well that ends well.*

* Publication date: **Feb 22, 2026**
* Last updated: **Feb 23, 2026**
* ***Information in this article might be very out of date.***

Hi. It's me, per(s)fidious. I am one of the four (now five) Skymu developers. I was just sent a link to a code review of Skymu ([https://epicness.wtf/blog/skymu.html](https://epicness.wtf/blog/skymu.html)) by @ep.ic.ne.ss, and I have some thoughts:

- I am honored (if not embarassed) that someone would take the time to review our code. It does seem to be a very attentive and detailed review which gets some things correct that we have fixed, and I thank Epicness for his efforts and help. However, there are some misunderstandings and inaccuracies in the review as it currently stands.
- In the opening, Epicness accuses me of "complaining" that a project wasn't on .NET 6. I was merely trying to find out the reasons of the dev (who is a friend) for staying on NET 10 and thought that I may convince him to move to NET 6, as I wasn't aware of any significant differences between the two. After some conversation, the developer filled me in to the fact that he was using some components not supported on NET 6, and that was that. I asked the developer in question later and he didn't have any problem with our civil discussion, it was just epicness who saw the conversation and took an issue.
- Epicness has told me that he plans to write a follow-up and edit his original review, which is very nice of him. However I really do wish he came to us with these problems personally, so we could have constructively fixed them, instead of making a public blog post without informing us of it before or afterwards.
- Goes without saying, but DO NOT HARASS Epicness or anyone else named in this text.

So, let's look at his points one by one. The headers here correspond to the headers in Epicness's own critique.

## 1) Versioning and Codenames - Misunderstanding

There does seem to be a misunderstanding here; we did use only codenames in the beginning, but we have since switched to a hybrid number/name system, which is exactly what Epicness suggested in his review - he must have either missed it or started writing the review some time ago. The latest release at time of writing is known as v0.2.1 "Chamonix Banana Cake".

## 2) The Near-Crisis - Misunderstanding

The Wormhole code is and has always been inactive. It was there for me to test and was never actually shipped in a production build. There are no references to that method. Proof-of-concept, as you could say, for my sole use. The embedded Python runtime it depended on has been removed and I'm surprised it's still in the codebase. The compiler does not care about abandoned code. Even if it was runnable, it would likely not do anything as the Python runtime it called no longer exists in Skymu.

**TL:DR:** If that code was actually active, it may pose a threat. But it isn't, and the code is not even compiled.

## 3) A Lesson in Rickrolling your Friends - Valid (but..)

This is a valid point - a malicious link disguised as Markdown could be clicked on. However, Skype itself exhibited this behavior, along with numerous other clients like Aerochat and Discord Messenger, and we also plan to add a confirmation dialog for clicking on such links just to be on the safe side. Thanks for pointing this out, epicness.

## 4) A Crisis in the Making - Unsure as of yet

As epicness himself said, the Clickables do not have handlers, so they pose no threat. However, they won't pose a threat once they have handlers either: User pings (will) go through the profile fetching pipeline, i.e. the value of your item is an identifier like "1234", and it fetches the user's profile by calling hypothetical function GetProfileData(1234), just like how Discord and other clients do it.

## 5) A Discussion on Language Grammars - Valid

Epicness makes a completely valid point. The shitty regex parsing is code from an older project of mine that I brought into Skymu. I have known that it absolutely needs to go for some time. However, it is not a priority as it poses no security or severe usability threat - but it will go, nonetheless.

## 6) A Bit of Shade - Misunderstanding

This was an inside joke based on a debate from early 2025. It's one of our many easter eggs. None of the APIs in that image are currently being used. However, we plan to move to a deriative of Cobble. SeanKype is a comprehensive API and project under the Skymu umbrella. Epicness also quotes the logo drama here, although he doesn't quite have all the pieces to the story - we had an internal debate over two developers having made logos and over which logo to use, which was followed by a developer saying he'd leave if his logo was not used, which was followed by me saying I'd leave if my API was not used, luckily, both are behind us, that developer's logo is being retired and my API is being retired.

## 7) The Protocol Plugins - Misunderstanding for Matrix, Valid for XMPP

The Matrix plugin absolutely implements the protocol. Recently, support was added for authentication via the Beeper multiprotocol service, as an alternative to Matrix authentication. You don't have to use Beeper and it is in no way an abstraction or bridge to Matrix. Epicness has told me that this was an oversight, which is absolutely understandable, I make a ton of oversights too, lol!

The XMPP plugin is proof-of-concept. It is not intended for actual use, but for testing and debugging. I should have made this more clear and I will.

## 8) Conclusion

There are things that Epicness has got right on the money, especially regarding certain coding practices (regex comes to mind), but there are also quite a few misunderstandings in his review, especially concerning the drama that happened, our versioning system, and some other things in the code. We are aware our code is messy, and that's why it hasn't been open-sourced yet. I think a lot of it stems from the differences of serious vs. hobby projects. Skymu is a hobby project. We do it for fun. Not to sell to customers, and that's why acronyms like B.A.S.E.D. are fine; because it's fun. That's why shipping beta features is fine, because there is no boss to answer to. And that's why the code may not implement best practices in certain selected portions; of course we would like it to, but the priority is adding whatever features we want and we think the community would want.

I would like to thank Epicness for his very insightful and detailed review, but also express regret that he didn't come to us with his concerns before posting it, as some misconceptions would have been cleared up.