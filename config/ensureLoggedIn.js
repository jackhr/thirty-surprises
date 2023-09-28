module.exports = function(req, res, next) {
    if (!req.session.loggedIn) {
        if (req.xhr) return res.json({ loggedOut: true });
        return res.redirect('/');
    }
    next();
};