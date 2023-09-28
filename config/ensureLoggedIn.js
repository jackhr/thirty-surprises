module.exports = function(req, res, next) {
    if (!req.session.loggedIn) return res.redirect('/');
    next();
};