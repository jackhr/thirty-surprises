const Surprise = require('../models/surprise');

module.exports = {
    index
}

async function index(req, res) {
    const surprises = await Surprise.find({});
    res.render('admin/index', {
        surprises,
        admin: req.session.user,
        surprisesLeft: undefined
    });
}