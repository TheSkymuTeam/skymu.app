function getQueryParam(name) {
    var search = window.location.search.substring(1);
    var pairs = search.split('&');
    for (var i = 0; i < pairs.length; i++) {
        var pair = pairs[i].split('=');
        if (decodeURIComponent(pair[0]) === name) {
            return pair[1] ? decodeURIComponent(pair[1]) : '';
        }
    }
    return null;
}

function levenshtein(a, b) {
    var dp = [];
    for (var i = 0; i <= a.length; i++) {
        dp[i] = [i];
        for (var j = 1; j <= b.length; j++) {
            dp[i][j] = i === 0 ? j :
                Math.min(dp[i-1][j] + 1, dp[i][j-1] + 1,
                    dp[i-1][j-1] + (a[i-1] === b[j-1] ? 0 : 1));
        }
    }
    return dp[a.length][b.length];
}

function loadWiki() {
    var container = document.getElementById('wiki-content');
    var pageName = window.location.pathname.split('/').filter(Boolean).pop().toLowerCase();
    if (pageName === 'wiki') pageName = 'home';

    tryLoad(pageName, container);
}

function tryLoad(pageName, container) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/wiki/pages/' + pageName + '.md', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                container.innerHTML = marked(xhr.responseText);
                document.title = 'Skymu Wiki - ' + pageName.charAt(0).toUpperCase() + pageName.slice(1);
            } else {
                tryFuzzy(pageName, container);
            }
        }
    };
    xhr.send();
}

function tryFuzzy(pageName, container) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/wiki/pages/home.md', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                var index = {};
                var re = /\[([^\]]+)\]\(([^)]+)\)/g;
                var match;
                while ((match = re.exec(xhr.responseText)) !== null) {
                    var desc = match[1], href = match[2];
                    // skip external links
                    if (href.indexOf('http') === 0) continue;
                    index[href] = desc;
                }

                var pages = Object.keys(index);
                var best = null, bestScore = Infinity;
                for (var i = 0; i < pages.length; i++) {
                    var score = levenshtein(pageName, pages[i]);
                    if (score < bestScore) { bestScore = score; best = pages[i]; }
                }
                if (best && bestScore <= 2) {
                    container.innerHTML = '<p>Did you mean <a href="/wiki/' + best + '">' + best + '</a> &mdash; <em>' + index[best] + '</em>?</p>';
                } else {
                    container.innerHTML = '<h1>Oops!</h1><p>We can\'t find that page.</p><a href="/wiki/">Back to Home</a>';
                }
            } else {
                container.innerHTML = '<h1>Oops!</h1><p>We can\'t find that page.</p><a href="/wiki/">Back to Home</a>';
            }
        }
    };
    xhr.send();
}loadWiki();