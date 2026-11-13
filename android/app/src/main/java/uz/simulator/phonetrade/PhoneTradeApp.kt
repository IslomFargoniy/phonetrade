package uz.simulator.phonetrade

import android.app.Application
import uz.simulator.phonetrade.data.ApiClient
import uz.simulator.phonetrade.data.Session

/** Ilova bo'yicha yagona obyektlar (DI kutubxonasisiz). */
class PhoneTradeApp : Application() {
    lateinit var session: Session
        private set
    lateinit var client: ApiClient
        private set

    override fun onCreate() {
        super.onCreate()
        session = Session(this)
        client = ApiClient(session)
    }
}
