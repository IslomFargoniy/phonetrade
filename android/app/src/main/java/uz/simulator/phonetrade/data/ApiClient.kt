package uz.simulator.phonetrade.data

import com.google.gson.FieldNamingPolicy
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.HttpException
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import uz.simulator.phonetrade.BuildConfig
import java.io.IOException
import java.util.concurrent.TimeUnit

/**
 * Server qaytargan xato. `code` o'zgarmas (docs/openapi.yaml), `message` foydalanuvchiga ko'rsatiladi.
 */
class ApiException(
    val code: String,
    override val message: String,
    val status: Int = 0,
) : Exception(message) {
    /** Aloqa uzilgan yoki server xatosi: natija noma'lum, o'sha Idempotency-Key bilan qayta urinish kerak. */
    val isRetryable: Boolean get() = status == 0 || status >= 500 || status == 409
}

class ApiClient(private val session: Session) {
    val gson: Gson = GsonBuilder()
        .setFieldNamingPolicy(FieldNamingPolicy.LOWER_CASE_WITH_UNDERSCORES)
        .create()

    private val headers = Interceptor { chain ->
        val request = chain.request().newBuilder().header("Accept", "application/json")

        session.token.value?.let { request.header("Authorization", "Bearer $it") }
        session.branchId?.let { request.header("X-Branch-Id", it.toString()) }

        chain.proceed(request.build())
    }

    private val http = OkHttpClient.Builder()
        .addInterceptor(headers)
        .apply {
            if (BuildConfig.DEBUG) {
                // Sarlavhalar yozilmaydi: token logga tushmasin.
                addInterceptor(HttpLoggingInterceptor().setLevel(HttpLoggingInterceptor.Level.BASIC))
            }
        }
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(60, TimeUnit.SECONDS)
        .build()

    val api: Api = Retrofit.Builder()
        .baseUrl(BuildConfig.API_URL)
        .client(http)
        .addConverterFactory(GsonConverterFactory.create(gson))
        .build()
        .create(Api::class.java)

    /** So'rovni bajaradi va har qanday xatoni [ApiException] ga aylantiradi. */
    suspend fun <T> call(block: suspend Api.() -> T): T {
        try {
            return api.block()
        } catch (error: HttpException) {
            val parsed = runCatching {
                gson.fromJson(error.response()?.errorBody()?.string(), ApiError::class.java)
            }.getOrNull()

            if (error.code() == 401 && session.token.value != null) {
                session.signOut()
            }

            val message = parsed?.errors?.values?.firstOrNull()?.firstOrNull()
                ?: parsed?.message?.takeIf { it.isNotBlank() }
                ?: when (error.code()) {
                    403 -> "Bu amalga ruxsat yo'q."
                    404 -> "Topilmadi."
                    429 -> "Juda ko'p urinish. Birozdan keyin qayta urinib ko'ring."
                    else -> "Server xatosi (${error.code()}). Qayta urinib ko'ring."
                }

            throw ApiException(parsed?.code ?: "HTTP_${error.code()}", message, error.code())
        } catch (_: IOException) {
            throw ApiException("NETWORK", "Internet aloqasi yo'q. Qayta urinib ko'ring.")
        }
    }
}
