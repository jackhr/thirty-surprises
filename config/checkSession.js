module.exports = function(req, res, next) {
    let sessionHasExpired = false;
    try {
        sessionHasExpired = 
        req.session &&
        req.session.cookie &&
        req.session.cookie._expires &&
        req.session.cookie._expires < new Date();
    } catch(err) {
        console.log(err);
        sessionHasExpired = true;
    }
    if (sessionHasExpired) {
        req.session.regenerate();
        req.session.user = null;
        req.session.token = null;
        req.session.loggedIn = null;
        if (req.xhr) return res.json({ loggedOut: true });
    }
    next();
};