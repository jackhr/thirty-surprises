const Surprise = require('../models/surprise');

module.exports = {
    index
}

async function index(req, res) {
    const surprisesWithRevealDate = await Surprise.find({ revealDate: { $ne: null } }).sort({ revealDate: 1 })
    const surprisesWithoutRevealDate = await Surprise.find({ revealDate: null })
    const surprises = [...surprisesWithRevealDate, ...surprisesWithoutRevealDate];
    res.render('admin/index', {
        surprises,
        admin: req.session.user,
        surprisesLeft: undefined
    });
}